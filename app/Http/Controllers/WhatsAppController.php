<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    /**
     * Display a listing of WhatsApp messages (sent logs and incoming messages).
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $selectedPhone = $request->input('phone');
        $activeDeviceId = $request->input('device_id');

        // 1. Fetch all devices from the API to show their statuses and fill the sender dropdown
        $devices = WhatsAppService::getDevices();

        // 2. Determine which device is active for the chat list view
        if (!$activeDeviceId && !empty($devices)) {
            // Find first connected device, otherwise first device
            $connectedDevice = collect($devices)->firstWhere('connected', true);
            $activeDeviceId = $connectedDevice ? $connectedDevice['device_id'] : $devices[0]['device_id'];
        }

        // 3. Fetch chats from GOWA API (fallback to local DB if empty)
        $chats = collect();
        $gowaChats = $activeDeviceId ? WhatsAppService::getChats($activeDeviceId) : [];

        $unreadCounts = WhatsAppMessage::query()
            ->select('phone', \DB::raw('count(*) as count'))
            ->where('direction', 'inbound')
            ->where('status', 'unread')
            ->groupBy('phone')
            ->pluck('count', 'phone');

        if (!empty($gowaChats)) {
            foreach ($gowaChats as $c) {
                $jid = $c['jid'] ?? '';
                $phone = WhatsAppService::cleanJid($jid);
                if (!$phone) continue;

                $chats->push((object)[
                    'phone' => $phone,
                    'name' => $c['name'] ?? '',
                    'message' => 'Buka percakapan...',
                    'direction' => 'inbound',
                    'created_at' => isset($c['last_message_time']) ? \Carbon\Carbon::parse($c['last_message_time']) : now(),
                    'status' => 'read',
                    'device_id' => $activeDeviceId,
                    'unread_count' => $unreadCounts[$phone] ?? 0,
                ]);
            }

            if ($search) {
                $chats = $chats->filter(function ($c) use ($search) {
                    return str_contains(strtolower($c->phone), strtolower($search)) || 
                           str_contains(strtolower($c->name ?? ''), strtolower($search));
                });
            }
        } else {
            // Fallback to local DB messages
            $latestMessages = \DB::table('whatsapp_messages')
                ->select('phone', \DB::raw('MAX(id) as max_id'))
                ->groupBy('phone');

            $chatsQuery = WhatsAppMessage::query()
                ->joinSub($latestMessages, 'latest', function ($join) {
                    $join->on('whatsapp_messages.id', '=', 'latest.max_id');
                })
                ->orderBy('whatsapp_messages.created_at', 'desc');

            if ($search) {
                $chatsQuery->where(function ($q) use ($search) {
                    $q->where('whatsapp_messages.phone', 'like', '%' . $search . '%')
                      ->orWhere('whatsapp_messages.name', 'like', '%' . $search . '%')
                      ->orWhere('whatsapp_messages.message', 'like', '%' . $search . '%');
                });
            }
            $dbChats = $chatsQuery->get();
            foreach ($dbChats as $dc) {
                $dc->unread_count = $unreadCounts[$dc->phone] ?? 0;
                $chats->push($dc);
            }
        }

        // 4. Determine which chat is active/selected
        if (!$selectedPhone && $chats->isNotEmpty()) {
            $selectedPhone = $chats->first()->phone;
        }

        // Mark active conversation messages as read
        if ($selectedPhone) {
            WhatsAppMessage::query()
                ->where('phone', $selectedPhone)
                ->where('direction', 'inbound')
                ->where('status', 'unread')
                ->update(['status' => 'read']);
            
            // Update unread count for active conversation in the loaded list
            $activeChat = $chats->firstWhere('phone', $selectedPhone);
            if ($activeChat) {
                $activeChat->unread_count = 0;
            }
        }

        // 5. Fetch message history for the selected chat
        $messages = collect();
        $selectedChatInfo = null;

        if ($selectedPhone) {
            $gowaMessages = [];
            if ($activeDeviceId) {
                $selectedJid = WhatsAppService::formatJid($selectedPhone);
                $gowaMessages = WhatsAppService::getChatMessages($activeDeviceId, $selectedJid);
            }

            if (!empty($gowaMessages)) {
                foreach ($gowaMessages as $m) {
                    $messages->push((object)[
                        'id' => $m['id'] ?? '',
                        'message_id' => $m['id'] ?? '',
                        'phone' => $selectedPhone,
                        'message' => $m['content'] ?? '',
                        'direction' => ($m['is_from_me'] ?? false) ? 'outbound' : 'inbound',
                        'status' => 'read',
                        'error_message' => null,
                        'created_at' => isset($m['timestamp']) ? \Carbon\Carbon::parse($m['timestamp']) : now(),
                    ]);
                }
            } else {
                // Fallback to local DB history
                $messages = WhatsAppMessage::query()
                    ->where('phone', $selectedPhone)
                    ->orderBy('created_at', 'asc')
                    ->get();
            }

            // Find selected chat name
            $selectedName = '';
            // Try local DB first
            $selectedName = WhatsAppMessage::where('phone', $selectedPhone)->whereNotNull('name')->where('name', '!=', '')->latest()->value('name') ?: '';
            
            // Try GOWA list next
            if (!$selectedName) {
                $gowaItem = $chats->firstWhere('phone', $selectedPhone);
                if ($gowaItem && !empty($gowaItem->name)) {
                    $selectedName = $gowaItem->name;
                }
            }

            $selectedChatInfo = (object)[
                'phone' => $selectedPhone,
                'name' => $selectedName,
                'device_id' => $activeDeviceId,
                'unread_count' => 0,
            ];
        }

        // If the selected chat is not in the chats list, prepend it
        if ($selectedPhone) {
            $hasChat = $chats->contains(fn($c) => $c->phone === $selectedPhone);
            if (!$hasChat) {
                $chats->prepend((object)[
                    'phone' => $selectedPhone,
                    'name' => $selectedChatInfo->name ?? '',
                    'message' => 'Buka percakapan...',
                    'direction' => 'inbound',
                    'created_at' => now()->subYear(), // Will be updated in step 6
                    'status' => 'read',
                    'device_id' => $activeDeviceId,
                    'unread_count' => 0,
                ]);
            }
        }

        // 6. Update chat list items with latest message text and details
        $localLatest = WhatsAppMessage::query()
            ->whereIn('phone', $chats->pluck('phone'))
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('phone')
            ->keyBy('phone');

        foreach ($chats as $chat) {
            // For active chat, use the latest live message from GOWA if available
            if ($chat->phone === $selectedPhone && $messages->isNotEmpty()) {
                $latestMsg = $messages->last();
                $chat->message = $latestMsg->message;
                $chat->direction = $latestMsg->direction;
                $chat->status = $latestMsg->status;
                $chat->created_at = $latestMsg->created_at;
            } 
            // For other chats, fall back to local DB if we have records
            elseif (isset($localLatest[$chat->phone])) {
                $lm = $localLatest[$chat->phone];
                $chat->message = $lm->message;
                $chat->direction = $lm->direction;
                $chat->status = $lm->status;
                $chat->created_at = $lm->created_at;
            }
        }

        // 7. Sort entire chat list by latest message time (created_at DESC)
        $chats = $chats->sortByDesc('created_at')->values();

        return view('pages.whatsapp.index', [
            'title' => 'WhatsApp Gateway',
            'chats' => $chats,
            'messages' => $messages,
            'selectedPhone' => $selectedPhone,
            'selectedChatInfo' => $selectedChatInfo,
            'devices' => $devices,
            'activeDeviceId' => $activeDeviceId,
            'whatsappApiUrl' => \App\Models\ApplicationSetting::query()->where('key', 'whatsapp_api_url')->value('value') ?: 'http://localhost:3000',
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Send a test message from the UI.
     */
    public function send(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string',
            'phone' => 'required|string|min:8',
            'message' => 'required|string',
        ]);

        $deviceId = $request->input('device_id');
        $phone = $request->input('phone');
        $messageText = $request->input('message');

        // Create log record
        $msgLog = WhatsAppMessage::create([
            'device_id' => $deviceId,
            'phone' => WhatsAppService::formatJid($phone),
            'message' => $messageText,
            'direction' => 'outbound',
            'status' => 'pending',
        ]);

        // Call Service
        $result = WhatsAppService::sendMessage($deviceId, $phone, $messageText);

        if ($result['success']) {
            $msgLog->update([
                'message_id' => $result['message_id'],
                'status' => 'sent',
            ]);
            return back()->with('success', 'Pesan WhatsApp berhasil dikirim.');
        }

        $msgLog->update([
            'status' => 'failed',
            'error_message' => $result['error'] ?? 'Gagal mengirim pesan',
        ]);

        return back()->withErrors(['error' => 'Gagal mengirim WhatsApp: ' . ($result['error'] ?? 'Unknown Error')]);
    }

    /**
     * Webhook receiver for go-whatsapp-web-multidevice events.
     */
    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');

        // Verify HMAC if a signature is provided (optional layer of security)
        if ($signature && !WhatsAppService::verifyWebhookSignature($payload, $signature)) {
            Log::warning('WhatsApp Webhook: Invalid signature received.');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $data = json_decode($payload, true);
        if (!$data || !isset($data['event'])) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        Log::info('WhatsApp Webhook received event: ' . $data['event']);

        $event = $data['event'];
        $deviceId = $data['device_id'] ?? null;
        $sessionId = $data['session_id'] ?? null;
        $eventPayload = $data['payload'] ?? [];

        switch ($event) {
            case 'message':
                $isFromMe = $eventPayload['is_from_me'] ?? false;
                $messageId = $eventPayload['id'] ?? null;
                $phoneJid = $eventPayload['from'] ?? '';
                $name = $eventPayload['from_name'] ?? null;
                $body = $eventPayload['body'] ?? '';
                $timestamp = $eventPayload['timestamp'] ?? null;

                if (!$messageId) {
                    break;
                }

                $phone = WhatsAppService::cleanJid($phoneJid);
                $direction = $isFromMe ? 'outbound' : 'inbound';
                $status = $isFromMe ? 'sent' : 'unread';

                // Use updateOrCreate to avoid duplicates
                WhatsAppMessage::updateOrCreate(
                    ['message_id' => $messageId],
                    [
                        'device_id' => $deviceId,
                        'session_id' => $sessionId,
                        'phone' => $phone,
                        'name' => $name,
                        'message' => $body,
                        'direction' => $direction,
                        'status' => $status,
                        'created_at' => $timestamp ? date('Y-m-d H:i:s', strtotime($timestamp)) : now(),
                    ]
                );
                break;

            case 'message.ack':
                $messageIds = $eventPayload['ids'] ?? [];
                $receiptType = $eventPayload['receipt_type'] ?? null;

                if (!empty($messageIds) && $receiptType) {
                    WhatsAppMessage::whereIn('message_id', $messageIds)
                        ->update(['status' => $receiptType]);
                }
                break;

            default:
                Log::info('WhatsApp Webhook: Unhandled event: ' . $event);
                break;
        }

        return response()->json(['status' => 'success']);
    }
}
