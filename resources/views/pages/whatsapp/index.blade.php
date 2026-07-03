@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="WhatsApp Gateway" />

    <div x-data="{ openModal: false }" class="grid grid-cols-1 gap-6 xl:grid-cols-4 h-[calc(100vh-230px)] min-h-[500px] overflow-hidden">
        
        <!-- Column 1: Devices list (Left) -->
        <div class="xl:col-span-1 flex flex-col h-full border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 rounded-2xl overflow-hidden shadow-xs min-h-0">
            <div class="p-4 border-b border-gray-200 dark:border-gray-800 shrink-0">
                <h2 class="text-md font-semibold text-gray-900 dark:text-white">Daftar Device</h2>
                <p class="text-3xs text-gray-400">WhatsApp Gateway (GOWA) active sessions</p>
            </div>
            
            <div class="flex-1 overflow-y-auto p-4 space-y-3 min-h-0">
                @forelse ($devices as $device)
                    @php
                        $isConnected = $device['connected'] ?? false;
                        $jid = $device['jid'] ?? '';
                        $deviceId = $device['device_id'] ?? '';
                        $name = $device['name'] ?? '';
                        $cleanPhone = $jid ? \App\Services\WhatsAppService::cleanJid($jid) : '';
                        $isActiveDevice = ($activeDeviceId ?? '') === $deviceId;
                    @endphp
                    <a href="{{ route('whatsapp.index', ['device_id' => $deviceId]) }}"
                        class="flex items-center justify-between rounded-lg border p-3 block transition {{ $isActiveDevice ? 'border-brand-500 bg-brand-50/20 dark:bg-brand-500/10' : 'border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-white/[0.02] hover:bg-gray-100 dark:hover:bg-white/[0.04]' }}">
                        <div class="min-w-0 flex-1 pr-2">
                            <span class="block font-semibold text-sm text-gray-800 dark:text-white/90 truncate" title="{{ $name ?: $deviceId }}">
                                {{ $name ?: $deviceId }}
                            </span>
                            <span class="block text-2xs text-gray-400 truncate mt-0.5" title="{{ $jid ?: 'Session: ' . $deviceId }}">
                                {{ $cleanPhone ? '+' . $cleanPhone : 'ID: ' . $deviceId }}
                            </span>
                        </div>
                        <div class="shrink-0">
                            @if ($isConnected)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-success-50 px-2 py-1 text-2xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">
                                    <span class="size-1.5 rounded-full bg-success-600 dark:bg-success-400"></span>
                                    Connected
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-error-50 px-2 py-1 text-2xs font-medium text-error-700 dark:bg-error-500/10 dark:text-error-400">
                                    <span class="size-1.5 rounded-full bg-error-600 dark:bg-error-400"></span>
                                    Offline
                                </span>
                            @endif
                        </div>
                    </a>
                @empty
                    <div class="text-center py-10">
                        <svg class="mx-auto size-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Tidak ada device.</p>
                    </div>
                @endforelse
            </div>

            <div class="p-4 border-t border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-white/[0.01] shrink-0">
                <a href="{{ $whatsappApiUrl }}" target="_blank" class="w-full flex items-center justify-center gap-2 rounded-lg border border-brand-500 bg-transparent px-4 py-2.5 text-xs font-semibold text-brand-600 hover:bg-brand-50/50 dark:text-brand-400 dark:hover:bg-brand-500/10 transition">
                    <svg class="size-4 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                    Hubungkan Device (Buka GOWA)
                </a>
            </div>
        </div>

        <!-- Column 2 & 3: Chat List & Conversation Thread (Right) -->
        <div class="xl:col-span-3 flex flex-col lg:flex-row rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-gray-900 overflow-hidden h-full min-h-0">
            
            <!-- Left Side: Chat List (1/3 Width) -->
            <div class="w-full lg:w-1/3 border-r border-gray-200 dark:border-gray-800 flex flex-col h-full overflow-hidden min-h-0">
                
                <!-- Search bar & New Chat button -->
                <div class="p-4 border-b border-gray-200 dark:border-gray-800 space-y-3 shrink-0">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Chat</h2>
                        @can('whatsapp.send')
                            <button @click="openModal = true" class="rounded-full bg-brand-500 p-1.5 text-white hover:bg-brand-600 transition" title="Kirim Pesan Baru">
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </button>
                        @endcan
                    </div>
                    <form method="GET" action="{{ route('whatsapp.index') }}" class="relative">
                        @if (request('phone'))
                            <input type="hidden" name="phone" value="{{ request('phone') }}">
                        @endif
                        @if ($activeDeviceId)
                            <input type="hidden" name="device_id" value="{{ $activeDeviceId }}">
                        @endif
                        <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Cari percakapan..." class="form-control h-10 pl-9 pr-4 text-xs">
                        <span class="absolute left-3 top-3 text-gray-400">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                    </form>
                </div>

                <!-- List of Conversations (Scrollable) -->
                <div class="flex-1 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800 min-h-0">
                    @forelse ($chats as $chat)
                        @php
                            $isActive = $selectedPhone === $chat->phone;
                            $messageTime = $chat->created_at;
                            if ($messageTime->isToday()) {
                                $timeLabel = $messageTime->format('H:i');
                            } elseif ($messageTime->isYesterday()) {
                                $timeLabel = 'Kemarin';
                            } else {
                                $timeLabel = $messageTime->translatedFormat('d M');
                            }
                        @endphp
                        <a href="{{ route('whatsapp.index', ['phone' => $chat->phone, 'device_id' => $activeDeviceId, 'search' => request('search')]) }}"
                            class="flex items-start gap-3 p-4 transition text-left block {{ $isActive ? 'bg-brand-25 border-l-4 border-brand-500 dark:bg-white/[0.03]' : 'hover:bg-gray-50/70 dark:hover:bg-white/[0.01]' }}">
                            
                            <!-- Contact Avatar Placeholder -->
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-500/10 text-brand-600 dark:bg-brand-500/20 dark:text-brand-400 font-semibold text-sm font-outfit">
                                {{ strtoupper(mb_substr($chat->name ?: $chat->phone, 0, 1)) }}
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between">
                                    <span class="font-medium text-sm text-gray-900 dark:text-white truncate">
                                        {{ $chat->name ?: '+' . $chat->phone }}
                                    </span>
                                    <span class="text-3xs text-gray-400 shrink-0 ml-1">
                                        {{ $timeLabel }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between mt-1 gap-2">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate flex-1">
                                        @if ($chat->direction === 'outbound')
                                            <span class="text-brand-500 dark:text-brand-400">Anda:</span>
                                        @endif
                                        {{ $chat->message }}
                                    </p>
                                    @if (!empty($chat->unread_count) && $chat->unread_count > 0)
                                        <span class="inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-success-500 text-white text-3xs font-semibold font-outfit">
                                            {{ $chat->unread_count }}
                                        </span>
                                    @elseif ($chat->direction === 'outbound')
                                        @php
                                            $ackColor = match ($chat->status) {
                                                'pending' => 'text-gray-400',
                                                'sent' => 'text-gray-400',
                                                'delivered' => 'text-brand-400',
                                                'read' => 'text-success-500',
                                                'failed' => 'text-error-500',
                                                default => 'text-gray-400'
                                            };
                                        @endphp
                                        <span class="{{ $ackColor }} shrink-0">
                                            @if ($chat->status === 'read')
                                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z" /></svg>
                                            @elseif ($chat->status === 'failed')
                                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                                            @else
                                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="text-center py-10">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada percakapan.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Right Side: Message Thread (2/3 Width) -->
            <div class="w-full lg:w-2/3 flex flex-col h-full overflow-hidden min-h-0">
                @if ($selectedPhone)
                    
                    <!-- Chat Header -->
                    <div class="p-4 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between bg-gray-50/50 dark:bg-white/[0.01] shrink-0 font-outfit">
                        <div class="min-w-0">
                            <span class="block font-semibold text-gray-900 dark:text-white">
                                {{ $selectedChatInfo->name ?: '+' . $selectedPhone }}
                            </span>
                            <span class="block text-2xs text-gray-400 mt-0.5">
                                Phone: +{{ $selectedPhone }}
                            </span>
                        </div>
                        @if ($selectedChatInfo->device_id)
                            <div class="text-right shrink-0">
                                <span class="inline-block text-3xs bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-400 rounded px-2 py-0.5">
                                    Device: {{ \App\Services\WhatsAppService::cleanJid($selectedChatInfo->device_id) }}
                                </span>
                            </div>
                        @endif
                    </div>

                    <!-- Chat Message bubbles (Scrollable) -->
                    <div class="flex-1 p-4 overflow-y-auto space-y-4 bg-gray-50/30 dark:bg-black/10 min-h-0" id="chat-messages-container">
                        @foreach ($messages as $msg)
                            @php
                                $isFromMe = $msg->direction === 'outbound';
                            @endphp
                            <div class="flex {{ $isFromMe ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-[75%] flex flex-col {{ $isFromMe ? 'items-end' : 'items-start' }}">
                                    
                                    <!-- Message Bubble -->
                                    <div class="p-3 text-sm leading-relaxed rounded-xl {{ $isFromMe ? 'bg-brand-500 text-white rounded-tr-none shadow-sm' : 'bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 rounded-tl-none shadow-sm border border-gray-100 dark:border-gray-700/50' }}">
                                        <p class="whitespace-pre-line">{{ $msg->message }}</p>
                                    </div>
                                    
                                    <!-- Timestamp & Status -->
                                    <div class="flex items-center gap-1.5 mt-1 text-3xs text-gray-400">
                                        <span>{{ $msg->created_at->format('H:i') }}</span>
                                        @if ($isFromMe)
                                            @php
                                                $statusIconColor = match ($msg->status) {
                                                    'pending' => 'text-gray-400',
                                                    'sent' => 'text-gray-400',
                                                    'delivered' => 'text-brand-400',
                                                    'read' => 'text-success-500',
                                                    'failed' => 'text-error-500',
                                                    default => 'text-gray-400'
                                                };
                                            @endphp
                                            <span class="{{ $statusIconColor }}" title="Status: {{ $msg->status }}">
                                                @if ($msg->status === 'read')
                                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z" /></svg>
                                                @elseif ($msg->status === 'failed')
                                                    <span class="text-error-500 font-medium">Gagal</span>
                                                @elseif ($msg->status === 'delivered')
                                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                                @else
                                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                    
                                    @if ($msg->error_message)
                                        <span class="block mt-0.5 text-3xs text-error-500">
                                            Detail Error: {{ $msg->error_message }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Direct Message Reply Footer Composer -->
                    @can('whatsapp.send')
                        <form method="POST" action="{{ route('whatsapp.send') }}" class="p-4 border-t border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-white/[0.01] shrink-0">
                            @csrf
                            <input type="hidden" name="phone" value="{{ $selectedPhone }}">
                            <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
                                
                                <!-- Sender Device Selection -->
                                <div class="w-full sm:w-44 shrink-0">
                                    <select name="device_id" required class="form-control h-10 py-1 text-xs">
                                        @foreach ($devices as $device)
                                            @php
                                                $connected = $device['connected'] ?? false;
                                                $jid = $device['jid'] ?? '';
                                                $devId = $device['device_id'] ?? '';
                                                $label = ($device['name'] ?: $devId) . ($connected ? ' (Connected)' : ' (Offline)');
                                            @endphp
                                            <option value="{{ $devId }}" @selected($devId === $activeDeviceId)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Text Input -->
                                <div class="flex-1">
                                    <input type="text" name="message" required placeholder="Tulis pesan balasan..." autocomplete="off" class="form-control h-10 text-sm">
                                </div>

                                <!-- Submit Button -->
                                <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2.5 text-white hover:bg-brand-600 transition flex items-center justify-center shrink-0">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                    </svg>
                                </button>
                            </div>
                        </form>
                    @endcan

                @else
                    <!-- Placeholder when no chat selected -->
                    <div class="flex-1 flex flex-col items-center justify-center p-8 text-center bg-gray-50/10 dark:bg-black/5">
                        <svg class="size-16 text-gray-300 dark:text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                        <h3 class="mt-4 text-md font-medium text-gray-700 dark:text-gray-300">Pilih Percakapan</h3>
                        <p class="mt-2 text-sm text-gray-400">Silakan klik salah satu nomor kontak di sebelah kiri untuk melihat pesan masuk dan mengirim balasan secara instan.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Alpine.js Dialog Modal for Starting New Conversation -->
        <div x-show="openModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs transition" x-cloak>
            <div @click.away="openModal = false" class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-gray-800 border border-gray-200 dark:border-gray-700 transition transform">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Kirim Pesan WhatsApp Baru</h3>
                    <button @click="openModal = false" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5">
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <form method="POST" action="{{ route('whatsapp.send') }}" class="mt-4 space-y-4">
                    @csrf
                    
                    <label class="block">
                        <span class="form-label">Pilih Device Pengirim</span>
                        <select name="device_id" required class="form-control">
                            <option value="">-- Pilih Device --</option>
                            @foreach ($devices as $device)
                                @php
                                    $connected = $device['connected'] ?? false;
                                    $jid = $device['jid'] ?? '';
                                    $devId = $device['device_id'] ?? '';
                                    $label = ($device['name'] ?: $devId) . ($connected ? ' (Connected)' : ' (Offline)');
                                @endphp
                                <option value="{{ $devId }}" @selected($devId === $activeDeviceId)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="form-label">Nomor Penerima</span>
                        <input type="text" name="phone" required placeholder="Contoh: 08123456789 atau 628123456789" class="form-control">
                        <span class="mt-1 block text-2xs text-gray-400">Nomor akan otomatis diformat ke format internasional (+62)</span>
                    </label>

                    <label class="block">
                        <span class="form-label">Isi Pesan</span>
                        <textarea name="message" required rows="4" placeholder="Ketik pesan Anda di sini..." class="form-control h-auto py-2.5"></textarea>
                    </label>

                    <div class="flex justify-end gap-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                        <button type="button" @click="openModal = false" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                            Batal
                        </button>
                        <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 transition">
                            Kirim Pesan
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
@endsection

@push('scripts')
    <script>
        function scrollToBottom() {
            const container = document.getElementById('chat-messages-container');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        }

        window.addEventListener('load', function () {
            scrollToBottom();
            // Fallback timeout to scroll after styling and content is fully laid out
            setTimeout(scrollToBottom, 50);
            setTimeout(scrollToBottom, 200);
        });
    </script>
@endpush
