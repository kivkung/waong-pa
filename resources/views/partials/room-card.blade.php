<div class="col-12 col-md-6 col-lg-4">
    <article class="card room-card h-100 rounded-4">
        <div class="card-body p-4 d-flex flex-column">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="badge {{ $room->visibility === 'public' ? 'text-bg-success' : 'text-bg-secondary' }}">
                    {{ $room->visibility === 'public' ? 'สาธารณะ' : 'ส่วนตัว' }}
                </span>
                <i class="bi {{ $room->visibility === 'public' ? 'bi-globe2' : 'bi-lock' }} text-success" aria-hidden="true"></i>
            </div>
            <h3 class="card-title h5">{{ $room->name }}</h3>
            <p class="room-description text-secondary flex-grow-1">{{ \Illuminate\Support\Str::limit($room->description ?: 'ยังไม่มีรายละเอียด', 180) }}</p>
            <a class="btn btn-outline-success btn-sm align-self-start" href="{{ route('rooms.show', $room) }}">ดูรายละเอียด <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i><span class="visually-hidden"> {{ $room->name }}</span></a>
        </div>
    </article>
</div>
