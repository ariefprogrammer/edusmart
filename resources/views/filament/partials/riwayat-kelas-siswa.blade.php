<div style="padding: 10px 0; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
    <div style="position: relative;">
        @forelse($riwayat as $item)
            <div style="display: flex; position: relative; padding-bottom: 24px;">
                @if(! $loop->last)
                    <div style="position: absolute; left: 15px; top: 32px; bottom: 0; width: 2px; background-color: #d1d5db; z-index: 1;"></div>
                @endif

                <div style="position: relative; z-index: 2; flex-shrink: 0; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; border: 4px solid white; box-sizing: border-box; background-color: {{ $item->status === 'aktif' ? '#22c55e' : '#ef4444' }}; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                    @if($item->status === 'aktif')
                        <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                        </svg>
                    @else
                        <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    @endif
                </div>

                <div style="flex: 1; margin-left: 16px; background-color: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);">
                    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                <span style="font-size: 14px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: {{ $item->status === 'aktif' ? '#16a34a' : '#dc2626' }};">
                                    {{ $item->status }}
                                </span>
                                <span style="color: #d1d5db;">•</span>
                                <span style="font-size: 12px; font-weight: 500; color: #6b7280; display: flex; align-items: center; gap: 4px;">
                                    <svg style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    {{ $item->tanggal->format('d M Y') }}
                                </span>
                            </div>
                            
                            @if($item->keterangan)
                                <p style="font-size: 14px; color: #4b5563; margin: 0; line-height: 1.5;">
                                    {{ $item->keterangan }}
                                </p>
                            @endif
                        </div>

                        <div style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; height: max-content;">
                            <svg style="width: 14px; height: 14px; color: #9ca3af;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            <span style="font-size: 12px; font-weight: 500; color: #4b5563;">
                                {{ $item->diubahOleh->name ?? 'Sistem' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 32px 16px; background-color: #f9fafb; border: 2px dashed #e5e7eb; border-radius: 12px;">
                <svg style="width: 40px; height: 40px; color: #d1d5db; margin-bottom: 12px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p style="font-size: 14px; color: #6b7280; font-weight: 500; margin: 0;">Belum ada riwayat perubahan.</p>
            </div>
        @endforelse
    </div>
</div>