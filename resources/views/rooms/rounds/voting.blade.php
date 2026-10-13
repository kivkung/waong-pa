@if ($roundMembers !== null && $round->status !== 'cancelled')
    @php
        $title = 'จัดอันดับเวลาและโหวต';
        $phaseLabel = $round->phase;
        if ($round->phase === 'join') {
            $phaseLabel = 'เตรียมข้อมูล';
        } elseif ($round->phase === 'review') {
            $phaseLabel = 'ตรวจตัวเลือก';
        } elseif ($round->phase === 'voting') {
            $phaseLabel = 'เปิดโหวต';
        } elseif ($round->phase === 'final') {
            $phaseLabel = 'สรุปผลแล้ว';
            $title = 'ผลการนัดหมาย';
        }
        $repeatLabel = 'ทุกเดือน';
        if ($round->repeat_frequency === 'weekly') {
            $repeatLabel = 'ทุกสัปดาห์';
        }
    @endphp
    <section class="wa-panel mb-4" aria-labelledby="voting-title">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
            <div>
                <!-- <div class="wa-kicker">Join & Prepare → Review → Voting → Final</div> -->
                <h2 class="wa-panel-title mt-2" id="voting-title">{{ $title }}</h2>
                <p class="wa-muted small mb-0">คะแนนความพร้อม = จำนวนสมาชิกที่ว่างตลอดช่วงนัด · คะแนนเท่ากันเรียงเวลาเร็วกว่าไว้ก่อน</p>
            </div>
            <span class="badge text-bg-success">{{ $phaseLabel }}</span>
        </div>
        @error('candidate_id') <div class="alert alert-danger" role="alert">{{ $message }}</div> @enderror
        @if ($round->phase === 'final')
            @if ($winner)
                <div class="alert alert-success">
                    <strong>เวลาที่ได้รับเลือก: {{ $winner->start_at->format('d/m/Y H:i') }} – {{ $winner->end_at->format('H:i') }} น.</strong>
                    <div>ได้รับ {{ $winner->votes_count }} โหวต · ว่าง {{ $winner->available_count }}/{{ $participantCount }} คน</div>
                    @if ($round->repeat_frequency !== 'none')
                        <div class="mt-2">บันทึกใน My Schedule {{ $repeatLabel }} {{ $round->repeat_count }} ครั้ง รวมครั้งแรก</div>
                        <details class="mt-2">
                            <summary>ดูวันนัดหมายทั้งหมด</summary>
                            <ul class="mt-2 mb-0">
                                @foreach ($round->appointmentOccurrences($winner) as $occurrence)
                                    <li>{{ $occurrence['start_at']->format('d/m/Y H:i') }} – {{ $occurrence['end_at']->format('d/m/Y H:i') }} น.</li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                    @if ($winner->votes_count === 0)
                        <div class="small mt-1">ระบบเลือกอัตโนมัติเนื่องจากไม่มีผู้โหวต โดยเลือกเวลาที่สมาชิกว่างมากที่สุด หากเท่ากันเลือกเวลาเร็วกว่า</div>
                    @else
                        <div class="small mt-1">เลือกจากคะแนนโหวตสูงสุด หากเท่ากันใช้คะแนนความพร้อม แล้วเลือกเวลาเร็วกว่า</div>
                    @endif
                </div>
            @else
                <div class="wa-notice mb-3">@if ($round->candidates()->exists()) ไม่มีผู้โหวต จึงยังไม่มีเวลานัดที่ได้รับเลือก @else ไม่มีช่วงเวลาที่ผ่านเงื่อนไข จึงไม่สามารถสรุปเวลานัดได้ @endif เจ้าของห้องสามารถสร้างรอบใหม่ได้</div>
            @endif
        @elseif ($candidates === null)
            <div class="wa-notice">@if ($round->phase === 'join') ระบบจะวิเคราะห์เวลาหลังปิดรับสมาชิกและเก็บสำเนาตารางแล้ว @else กำลังรอสำเนาตารางเวลาเพื่อวิเคราะห์ความพร้อม @endif</div>
        @elseif ($round->phase === 'review')
            <div class="wa-notice mb-3">ตรวจตัวเลือกจากสำเนาตารางที่ล็อกแล้ว เริ่มโหวต {{ $round->voting_starts_at->format('d/m/Y H:i') }} น.</div>
        @elseif ($round->phase === 'voting')
            <div class="wa-notice mb-3">@if ($participant) แสดงเฉพาะเวลาที่คุณว่าง เลือกได้หนึ่งตัวเลือกและเปลี่ยนได้ @else คุณไม่ได้เข้าร่วมรอบนี้ จึงดูตัวเลือกได้แต่โหวตไม่ได้ @endif · ปิดโหวต {{ $round->final_starts_at->format('d/m/Y H:i') }} น.</div>
        @endif
        @if ($candidates !== null)
            @foreach ($candidates as $candidate)
                @php
                    $isSelected = (int) $selectedCandidateId === (int) $candidate->id;
                    $cardClass = '';
                    $buttonClass = 'btn-outline-success';
                    $buttonLabel = 'โหวตเวลานี้';
                    if ($selectedCandidateId) {
                        $buttonLabel = 'เปลี่ยนมาเลือกเวลานี้';
                    }
                    if ($isSelected) {
                        $cardClass = 'border-success bg-success-subtle';
                        $buttonClass = 'btn-success';
                        $buttonLabel = 'โหวตของคุณ ✓';
                    }
                    $availablePercent = 0;
                    if ($participantCount > 0) {
                        $availablePercent = round($candidate->available_count / $participantCount * 100);
                    }
                @endphp
                <div class="border rounded p-3 mb-3 {{ $cardClass }}">
                    <div class="d-flex justify-content-between gap-3 flex-wrap">
                        <div>
                            <strong>{{ $candidate->start_at->format('d/m/Y') }} · {{ $candidate->start_at->format('H:i') }} – {{ $candidate->end_at->format('H:i') }} น.</strong>
                            <div class="wa-muted small mt-1">คะแนนความพร้อม {{ $candidate->available_count }} · ว่าง {{ $candidate->available_count }}/{{ $participantCount }} คน ({{ $availablePercent }}%) · อาจารย์ {{ $candidate->professor_count }} คน</div>
                            @if ($round->phase === 'final') <div class="small mt-1">{{ $candidate->votes_count }} โหวต @if ($candidate->is_winner) · เวลาที่ได้รับเลือก @endif</div> @endif
                        </div>
                        @if ($participant && $round->phase === 'voting' && $round->status === 'active')
                            <form method="POST" action="{{ route('rooms.rounds.votes.store', [$room, $round]) }}">
                                @csrf
                                <input type="hidden" name="candidate_id" value="{{ $candidate->id }}">
                                <button class="btn {{ $buttonClass }}" type="submit">{{ $buttonLabel }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
            @if ($candidates->isEmpty())
                <p class="wa-muted">@if ($round->phase === 'voting' && $participant) ไม่มีตัวเลือกที่ผ่านเงื่อนไขและตรงกับเวลาว่างของคุณ @else ไม่มีช่วงเวลาที่ผ่านเงื่อนไขอาจารย์และระยะเวลานัด ลองปรับเงื่อนไขหรือสร้างรอบใหม่ @endif</p>
            @endif
            {{ $candidates->withQueryString()->links('pagination::bootstrap-5') }}
        @endif
    </section>
@endif
