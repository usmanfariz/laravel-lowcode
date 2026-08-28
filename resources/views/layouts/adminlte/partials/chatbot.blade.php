{{--
    Chatbot bantuan.

    Dipasang di layout, jadi ia ikut ke setiap halaman — pertanyaan cara pakai
    biasanya muncul justru saat sedang di tengah pekerjaan, bukan saat sedang
    membuka halaman bantuan.
--}}
<div class="lc-bot" id="lc-bot">
    <button type="button" class="lc-bot__toggle" title="Bantuan penggunaan"
            aria-label="Buka bantuan penggunaan">
        <i class="fas fa-question"></i>
    </button>

    <section class="lc-bot__panel" hidden aria-label="Bantuan penggunaan">
        <header class="lc-bot__head">
            <div class="lc-bot__title">
                Bantuan
                <small>Tanya cara memakai aplikasi ini</small>
            </div>
            <button type="button" class="lc-bot__icon" data-lc-bot="topics"
                    title="Telusuri semua topik" aria-label="Telusuri semua topik">
                <i class="fas fa-list-ul"></i>
            </button>
            <button type="button" class="lc-bot__icon" data-lc-bot="reset"
                    title="Mulai percakapan baru" aria-label="Mulai percakapan baru">
                <i class="fas fa-redo"></i>
            </button>
            <button type="button" class="lc-bot__icon" data-lc-bot="close"
                    title="Tutup" aria-label="Tutup bantuan">
                <i class="fas fa-times"></i>
            </button>
        </header>

        <div class="lc-bot__log" role="log" aria-live="polite"></div>

        <form class="lc-bot__form">
            <input type="text" class="lc-bot__input" maxlength="255" autocomplete="off"
                   placeholder="Tulis pertanyaan…" aria-label="Pertanyaan">
            <button type="submit" class="lc-bot__send" title="Kirim" aria-label="Kirim pertanyaan">
                <i class="fas fa-paper-plane"></i>
            </button>
        </form>
    </section>
</div>

@push('scripts')
<script>
    LcChatbot.init({
        routes: {
            topics: '{{ route('help.topics') }}',
            ask: '{{ route('help.ask') }}',
            article: '{{ route('help.article', ['id' => '__id__']) }}'
        }
    });
</script>
@endpush
