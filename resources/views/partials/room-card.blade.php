<div class="col-12 col-md-6 col-lg-4">
    <article class="card room-card transition-card h-100 rounded-4">
        <div class="card-body p-4">
            <span class="badge {{ $room->visibility === 'public' ? 'text-bg-success' : 'text-bg-secondary' }} mb-3">
                {{ $room->visibility === 'public' ? 'Public' : 'Private' }}
            </span>
            <h3 class="card-title h5">{{ $room->name }}</h3>
            <p class="room-description text-secondary">{{ \Illuminate\Support\Str::limit($room->description ?: 'ยังไม่มีรายละเอียด', 180) }}</p>
            <a class="btn btn-outline-success btn-sm" href="{{ route('rooms.show', $room) }}">ดูรายละเอียด<span class="visually-hidden"> {{ $room->name }}</span></a>
        </div>
    </article>
</div>
