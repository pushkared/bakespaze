<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageReaction;
use App\Models\User;
use App\Notifications\ChatMessageNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('chat.index', [
            'users' => $users,
        ]);
    }

    public function conversations(Request $request)
    {
        $user = $request->user();

        $conversations = $user->conversations()
            ->with(['participants:id,name,avatar_url', 'messages' => function ($q) {
                $q->latest()->limit(1);
            }])
            ->get()
            ->map(function (Conversation $conversation) use ($user) {
                $lastMessage = $conversation->messages->first();
                $title = $conversation->type === 'group'
                    ? ($conversation->name ?: 'Group Chat')
                    : $conversation->participants->firstWhere('id', '!=', $user->id)?->name;

                return [
                    'id' => $conversation->id,
                    'type' => $conversation->type,
                    'title' => $title ?: 'Direct Chat',
                    'avatar_url' => $conversation->type === 'group' ? $this->avatarUrl($conversation->avatar_url) : null,
                    'participants' => $conversation->participants->map(fn($p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'avatar_url' => $this->avatarUrl($p->avatar_url),
                    ])->values(),
                    'last_message' => $lastMessage ? [
                        'body' => $lastMessage->body,
                        'created_at' => $lastMessage->created_at->toDateTimeString(),
                    ] : null,
                    'sort_at' => ($lastMessage?->created_at ?? $conversation->updated_at ?? $conversation->created_at)?->toDateTimeString(),
                ];
            })
            ->sortByDesc('sort_at')
            ->values();

        return response()->json($conversations);
    }

    public function storeConversation(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:direct,group'],
            'name' => ['nullable', 'string', 'max:120'],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $user = $request->user();
        $participantIds = collect($data['participant_ids'])->unique()->filter(fn($id) => $id !== $user->id);

        if ($data['type'] === 'direct') {
            $peerId = $participantIds->first();
            if (!$peerId) {
                return response()->json(['message' => 'Select a user to start a direct chat.'], 422);
            }

            $existing = Conversation::where('type', 'direct')
                ->whereHas('participants', fn($q) => $q->where('users.id', $user->id))
                ->whereHas('participants', fn($q) => $q->where('users.id', $peerId))
                ->first();

            if ($existing) {
                return response()->json(['id' => $existing->id], 200);
            }
        }

        $conversation = Conversation::create([
            'name' => $data['type'] === 'group' ? $data['name'] : null,
            'type' => $data['type'],
            'created_by' => $user->id,
        ]);

        $allParticipants = $participantIds->push($user->id)->unique();
        $conversation->participants()->sync(
            $allParticipants->mapWithKeys(fn($id) => [$id => ['role' => $id === $user->id ? 'admin' : 'member', 'joined_at' => now()]])->all()
        );

        return response()->json(['id' => $conversation->id], 201);
    }

    public function messages(Request $request, Conversation $conversation)
    {
        $this->ensureParticipant($request->user(), $conversation);

        $messages = $conversation->messages()
            ->with(['sender:id,name,avatar_url', 'attachments', 'reactions', 'replyTo.sender:id,name,avatar_url'])
            ->orderBy('id')
            ->get()
            ->map(function (Message $message) {
                return $this->formatMessage($message);
            });

        return response()->json($messages);
    }

    public function storeMessage(Request $request, Conversation $conversation)
    {
        $this->ensureParticipant($request->user(), $conversation);

        $data = $request->validate([
            'body' => ['nullable', 'string'],
            'reply_to' => ['nullable', 'exists:messages,id'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        if (empty($data['body']) && !$request->hasFile('attachments')) {
            return response()->json(['message' => 'Message is empty.'], 422);
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'] ?? null,
            'reply_to_message_id' => $data['reply_to'] ?? null,
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('chat', 'public');
                MessageAttachment::create([
                    'message_id' => $message->id,
                    'user_id' => $request->user()->id,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        }

        $message->load(['sender:id,name,avatar_url', 'attachments', 'reactions', 'replyTo.sender:id,name,avatar_url']);

        $payload = $this->formatMessage($message);
        broadcast(new MessageSent($payload))->toOthers();

        try {
            $recipients = $conversation->participants()
                ->where('users.id', '!=', $request->user()->id)
                ->get();
            Notification::send($recipients, new ChatMessageNotification($message));
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json($payload, 201);
    }

    public function react(Request $request, Message $message)
    {
        $conversation = $message->conversation;
        $this->ensureParticipant($request->user(), $conversation);

        $data = $request->validate([
            'emoji' => ['required', 'string', 'max:24'],
        ]);

        MessageReaction::firstOrCreate([
            'message_id' => $message->id,
            'user_id' => $request->user()->id,
            'emoji' => $data['emoji'],
        ]);

        return response()->json(['status' => 'ok']);
    }

    public function unreact(Request $request, Message $message)
    {
        $conversation = $message->conversation;
        $this->ensureParticipant($request->user(), $conversation);

        $data = $request->validate([
            'emoji' => ['required', 'string', 'max:24'],
        ]);

        MessageReaction::where('message_id', $message->id)
            ->where('user_id', $request->user()->id)
            ->where('emoji', $data['emoji'])
            ->delete();

        return response()->json(['status' => 'ok']);
    }

    public function markRead(Request $request, Conversation $conversation)
    {
        $this->ensureParticipant($request->user(), $conversation);

        $data = $request->validate([
            'last_read_message_id' => ['nullable', 'integer'],
        ]);

        $conversation->participants()->updateExistingPivot($request->user()->id, [
            'last_read_message_id' => $data['last_read_message_id'] ?? null,
        ]);

        return response()->json(['status' => 'ok']);
    }

    public function updateConversation(Request $request, Conversation $conversation)
    {
        $this->ensureParticipant($request->user(), $conversation);
        abort_unless($conversation->type === 'group', 403);

        $isAdmin = $conversation->created_by === $request->user()->id
            || $conversation->participants()
                ->where('users.id', $request->user()->id)
                ->wherePivot('role', 'admin')
                ->exists();
        abort_unless($isAdmin, 403);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        if (array_key_exists('name', $data)) {
            $conversation->name = $data['name'];
        }

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('chat-icons', 'public');
            $conversation->avatar_url = $path;
        }

        $conversation->save();

        return response()->json([
            'id' => $conversation->id,
            'name' => $conversation->name,
            'avatar_url' => $this->avatarUrl($conversation->avatar_url),
        ]);
    }

    public function downloadAttachment(Request $request, MessageAttachment $attachment)
    {
        $conversation = $attachment->message->conversation;
        $this->ensureParticipant($request->user(), $conversation);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($attachment->path), 404);
        $path = $disk->path($attachment->path);
        $name = $attachment->original_name ?: basename($path);

        return response()->download($path, $name, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.addslashes($name).'"',
        ]);
    }

    public function previewAttachment(Request $request, MessageAttachment $attachment)
    {
        $conversation = $attachment->message->conversation;
        $this->ensureParticipant($request->user(), $conversation);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($attachment->path), 404);
        $path = $disk->path($attachment->path);
        $mime = $disk->mimeType($attachment->path) ?: 'application/octet-stream';

        return response()->file($path, ['Content-Type' => $mime]);
    }

    protected function ensureParticipant(User $user, Conversation $conversation): void
    {
        if (!$conversation->participants()->where('users.id', $user->id)->exists()) {
            abort(403);
        }
    }

    protected function formatMessage(Message $message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'user_id' => $message->user_id,
            'sender' => $message->sender?->name,
            'sender_avatar' => $this->avatarUrl($message->sender?->avatar_url),
            'body' => $message->body,
            'created_at' => $message->created_at->toDateTimeString(),
            'reply_to' => $message->replyTo ? [
                'id' => $message->replyTo->id,
                'sender' => $message->replyTo->sender?->name,
                'body' => $message->replyTo->body,
            ] : null,
            'attachments' => $message->attachments->map(fn($att) => [
                'id' => $att->id,
                'name' => $att->original_name,
                'mime' => $att->mime_type,
                'size' => $att->size,
                'preview_url' => route('chat.attachments.preview', $att),
                'url' => route('chat.attachments.download', $att),
            ])->values(),
            'reactions' => $message->reactions->map(fn($reaction) => [
                'emoji' => $reaction->emoji,
                'user_id' => $reaction->user_id,
            ])->values(),
        ];
    }

    protected function avatarUrl(?string $avatar): ?string
    {
        if (!$avatar) {
            return null;
        }
        if (Str::startsWith($avatar, ['http://', 'https://'])) {
            return $avatar;
        }
        return Storage::url($avatar);
    }
}
