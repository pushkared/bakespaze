<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Events\MessageReceiptUpdated;
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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

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
                ];
            })
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

        $conversation->load('participants');
        $readMap = $conversation->participants->mapWithKeys(fn($p) => [$p->id => (int) ($p->pivot->last_read_message_id ?? 0)]);
        $deliveredMap = $conversation->participants->mapWithKeys(fn($p) => [$p->id => (int) ($p->pivot->last_delivered_message_id ?? 0)]);

        $messages = $conversation->messages()
            ->with(['sender:id,name,avatar_url', 'attachments', 'reactions', 'replyTo.sender:id,name,avatar_url'])
            ->orderBy('id')
            ->get()
            ->map(function (Message $message) {
                return $this->formatMessage($message, $readMap, $deliveredMap, $conversation->participants);
            });

        return response()->json([
            'messages' => $messages,
            'read_map' => $readMap,
            'delivered_map' => $deliveredMap,
        ]);
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
        $conversation->load('participants');

        $readMap = $conversation->participants->mapWithKeys(fn($p) => [$p->id => (int) ($p->pivot->last_read_message_id ?? 0)]);
        $deliveredMap = $conversation->participants->mapWithKeys(fn($p) => [$p->id => (int) ($p->pivot->last_delivered_message_id ?? 0)]);
        $payload = $this->formatMessage($message, $readMap, $deliveredMap, $conversation->participants);
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

        $participant = $conversation->participants()->where('users.id', $request->user()->id)->first();
        $payload = [
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'last_read_message_id' => (int) ($participant?->pivot->last_read_message_id ?? 0),
            'last_delivered_message_id' => (int) ($participant?->pivot->last_delivered_message_id ?? 0),
        ];
        broadcast(new MessageReceiptUpdated($payload))->toOthers();

        return response()->json([
            'status' => 'ok',
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'last_read_message_id' => $data['last_read_message_id'] ?? null,
        ]);
    }

    public function markDelivered(Request $request, Conversation $conversation)
    {
        $this->ensureParticipant($request->user(), $conversation);

        $data = $request->validate([
            'last_delivered_message_id' => ['required', 'integer'],
        ]);

        $conversation->participants()->updateExistingPivot($request->user()->id, [
            'last_delivered_message_id' => $data['last_delivered_message_id'],
        ]);

        $participant = $conversation->participants()->where('users.id', $request->user()->id)->first();
        $payload = [
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'last_read_message_id' => (int) ($participant?->pivot->last_read_message_id ?? 0),
            'last_delivered_message_id' => (int) ($participant?->pivot->last_delivered_message_id ?? 0),
        ];
        broadcast(new MessageReceiptUpdated($payload))->toOthers();

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

    public function linkPreview(Request $request)
    {
        $data = $request->validate([
            'url' => ['required', 'url'],
        ]);

        $url = $data['url'];
        if (!$this->isSafeUrl($url)) {
            return response()->json(['error' => 'Invalid URL'], 422);
        }

        $preview = Cache::remember('link_preview_'.md5($url), now()->addHour(), function () use ($url) {
            try {
                $response = Http::timeout(6)->get($url);
                if (!$response->ok()) {
                    return null;
                }
                $html = $response->body();
                return $this->extractPreview($html, $url);
            } catch (\Throwable $e) {
                return null;
            }
        });

        if (!$preview) {
            return response()->json(['error' => 'No preview'], 404);
        }

        return response()->json($preview);
    }

    public function downloadAttachment(Request $request, MessageAttachment $attachment)
    {
        $conversation = $attachment->message->conversation;
        $this->ensureParticipant($request->user(), $conversation);

        return Storage::disk('public')->download($attachment->path, $attachment->original_name);
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

    protected function formatMessage(Message $message, $readMap = null, $deliveredMap = null, $participants = null): array
    {
        $status = null;
        if ($readMap && $deliveredMap && $participants && $message->sender?->id) {
            $senderId = $message->sender->id;
            $others = $participants->filter(fn($p) => $p->id !== $senderId);
            $seen = $others->isEmpty()
                ? false
                : $others->every(fn($p) => (int) ($readMap[$p->id] ?? 0) >= $message->id);
            $delivered = $others->isEmpty()
                ? false
                : $others->every(fn($p) => (int) ($deliveredMap[$p->id] ?? 0) >= $message->id);
            if ((int) $senderId === (int) auth()->id()) {
                $status = $seen ? 'seen' : ($delivered ? 'delivered' : 'sent');
            }
        }

        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'user_id' => $message->user_id,
            'sender' => $message->sender?->name,
            'sender_avatar' => $this->avatarUrl($message->sender?->avatar_url),
            'body' => $message->body,
            'created_at' => $message->created_at->toDateTimeString(),
            'status' => $status,
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

    protected function isSafeUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        if (!in_array($parts['scheme'], ['http', 'https'], true)) {
            return false;
        }
        $host = $parts['host'];
        $ip = gethostbyname($host);
        if ($ip === $host) {
            return false;
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        return true;
    }

    protected function extractPreview(string $html, string $url): ?array
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML($html);
        $xpath = new \DOMXPath($doc);

        $titleNode = $xpath->query("//meta[@property='og:title']/@content")->item(0)
            ?: $xpath->query("//title")->item(0);
        $descNode = $xpath->query("//meta[@property='og:description']/@content")->item(0)
            ?: $xpath->query("//meta[@name='description']/@content")->item(0);
        $imageNode = $xpath->query("//meta[@property='og:image']/@content")->item(0);

        $title = $titleNode ? trim($titleNode->nodeValue ?? $titleNode->textContent ?? '') : null;
        $description = $descNode ? trim($descNode->nodeValue ?? $descNode->textContent ?? '') : null;
        $image = $imageNode ? trim($imageNode->nodeValue ?? $imageNode->textContent ?? '') : null;

        if ($image) {
            $image = $this->resolveUrl($url, $image);
        }

        if (!$title && !$description && !$image) {
            return null;
        }

        return [
            'url' => $url,
            'title' => $title,
            'description' => $description,
            'image' => $image,
        ];
    }

    protected function resolveUrl(string $base, string $relative): string
    {
        if (Str::startsWith($relative, ['http://', 'https://'])) {
            return $relative;
        }
        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        if (Str::startsWith($relative, '//')) {
            return $scheme.':'.$relative;
        }
        if (Str::startsWith($relative, '/')) {
            return $scheme.'://'.$host.$relative;
        }
        $path = $parts['path'] ?? '/';
        $dir = Str::endsWith($path, '/') ? $path : dirname($path).'/';
        return $scheme.'://'.$host.$dir.$relative;
    }
}
