@if ($roundMembers !== null && $round->status !== 'cancelled')
    <section class="wa-panel mb-4" aria-labelledby="voting-title">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
            <div>
                <!-- <div class="wa-kicker">Join & Prepare → Review → Voting → Final</div> -->
                <h2 class="wa-panel-title mt-2" id="voting-title">{{ $round->phase === 'final' ? 'ผลการนัดหมาย' : 'จัดอันดับเวลาและโหวต' }}</h2>
                <p class="wa-muted small mb-0">คะแนนความพร้อม = จำนวนสมาชิกที่ว่างตลอดช่วงนัด · คะแนนเท่ากันเรียงเวลาเร็วกว่าไว้ก่อน</p>
            </div>
            <span class="badge text-bg-success">{{ ['join' => 'เตรียมข้อมูล', 'review' => 'ตรวจตัวเลือก', 'voting' => 'เปิดโหวต', 'final' => 'สรุปผลแล้ว'][$round->phase] ?? $round->phase }}</span>
        </div>
        @error('candidate_id') <div class="alert alert-danger" role="alert">{{ $message }}</div> @enderror
        @if ($round->phase === 'final')
            @if ($winner)
                <div class="alert alert-success">
                    <strong>เวลาที่ได้รับเลือก: {{ $winner->start_at->format('d/m/Y H:i') }} – {{ $winner->end_at->format('H:i') }} น.</strong>
                    <div>ได้รับ {{ $winner->votes_count }} โหวต · ว่าง {{ $winner->available_count }}/{{ $participantCount }} คน</div>
                    <div class="small mt-1">เลือกจากคะแนนโหวตสูงสุด หากเท่ากันใช้คะแนนความพร้อม แล้วเลือกเวลาเร็วกว่า</div>
                </div>
                <a class="btn btn-success mb-3" href="{{ route('rooms.rounds.calendar', [$room, $round]) }}">ดาวน์โหลดปฏิทิน .ics</a>
            @else
                <div class="wa-notice mb-3">{{ $round->candidates()->exists() ? 'ไม่มีผู้โหวต จึงยังไม่มีเวลานัดที่ได้รับเลือก' : 'ไม่มีช่วงเวลาที่ผ่านเงื่อนไข จึงไม่สามารถสรุปเวลานัดได้' }} เจ้าของห้องสามารถสร้างรอบใหม่ได้</div>
            @endif
        @elseif ($candidates === null)
            <div class="wa-notice">{{ $round->phase === 'join' ? 'ระบบจะวิเคราะห์เวลาหลังปิดรับสมาชิกและเก็บสำเนาตารางแล้ว' : 'กำลังรอสำเนาตารางเวลาเพื่อวิเคราะห์ความพร้อม' }}</div>
        @elseif ($round->phase === 'review')
            <div class="wa-notice mb-3">ตรวจตัวเลือกจากสำเนาตารางที่ล็อกแล้ว เริ่มโหวต {{ $round->voting_starts_at->format('d/m/Y H:i') }} น.</div>
        @elseif ($round->phase === 'voting')
            <div class="wa-notice mb-3">{{ $participant ? 'แสดงเฉพาะเวลาที่คุณว่าง เลือกได้หนึ่งตัวเลือกและเปลี่ยนได้' : 'คุณไม่ได้เข้าร่วมรอบนี้ จึงดูตัวเลือกได้แต่โหวตไม่ได้' }} · ปิดโหวต {{ $round->final_starts_at->format('d/m/Y H:i') }} น.</div>
        @endif
        @if ($candidates !== null)
            @foreach ($candidates as $candidate)
                <div class="border rounded p-3 mb-3 {{ (int) $selectedCandidateId === (int) $candidate->id ? 'border-success bg-success-subtle' : '' }}">
                    <div class="d-flex justify-content-between gap-3 flex-wrap">
                        <div>
                            <strong>{{ $candidate->start_at->format('d/m/Y') }} · {{ $candidate->start_at->format('H:i') }} – {{ $candidate->end_at->format('H:i') }} น.</strong>
                            <div class="wa-muted small mt-1">คะแนนความพร้อม {{ $candidate->available_count }} · ว่าง {{ $candidate->available_count }}/{{ $participantCount }} คน ({{ $participantCount ? round($candidate->available_count / $participantCount * 100) : 0 }}%) · อาจารย์ {{ $candidate->professor_count }} คน</div>
                            @if ($round->phase === 'final') <div class="small mt-1">{{ $candidate->votes_count }} โหวต {{ $candidate->is_winner ? '· เวลาที่ได้รับเลือก' : '' }}</div> @endif
                        </div>
                        @if ($participant && $round->phase === 'voting' && $round->status === 'active')
                            <form method="POST" action="{{ route('rooms.rounds.votes.store', [$room, $round]) }}">
                                @csrf
                                <input type="hidden" name="candidate_id" value="{{ $candidate->id }}">
                                <button class="btn {{ (int) $selectedCandidateId === (int) $candidate->id ? 'btn-success' : 'btn-outline-success' }}" type="submit">{{ (int) $selectedCandidateId === (int) $candidate->id ? 'โหวตของคุณ ✓' : ($selectedCandidateId ? 'เปลี่ยนมาเลือกเวลานี้' : 'โหวตเวลานี้') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
            @if ($candidates->isEmpty())
                <p class="wa-muted">{{ $round->phase === 'voting' && $participant ? 'ไม่มีตัวเลือกที่ผ่านเงื่อนไขและตรงกับเวลาว่างของคุณ' : 'ไม่มีช่วงเวลาที่ผ่านเงื่อนไขอาจารย์และระยะเวลานัด ลองปรับเงื่อนไขหรือสร้างรอบใหม่' }}</p>
            @endif
            {{ $candidates->withQueryString()->links('pagination::bootstrap-5') }}
        @endif
    </section>
@endif
