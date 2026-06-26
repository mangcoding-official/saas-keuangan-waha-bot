<footer id="footer" class="landing-footer">
    <div class="footer-wrapper">
        <div class="landing-footer-top">
            <div class="landing-footer-brand">
                <a href="{{ route('home') }}" class="landing-brand landing-brand-inverse" aria-label="Aplikasi Keuangan MACAU Bot">
                    <span class="landing-brand-mark">
                        <img src="{{ asset('images/macau-bot.svg') }}" alt="macau - aplikasi keuangan" aria-hidden="true">
                    </span>
                    <span class="landing-brand-text">MACAU</span>
                </a>
                <p>Solusi cerdas pencatatan keuangan harian melalui WhatsApp. Transparan, cepat, dan mudah digunakan untuk rumah tangga maupun bisnis kecil.</p>
            </div>

            <div class="landing-footer-columns">
                <div class="landing-footer-column">
                    <h3>Perusahaan</h3>
                    <a href="#footer">Tentang Kami</a>
                    <a href="#footer">Kontak</a>
                </div>

                <div class="landing-footer-column">
                    <h3>Produk</h3>
                    <a href="{{ route('home') }}#fitur">Fitur</a>
                </div>

                <div class="landing-footer-column landing-footer-subscribe">
                    <h3>Dapatkan Update</h3>
                    <form class="landing-subscribe-form" action="#" method="get">
                        <label class="sr-only" for="landing-email">Email Anda</label>
                        <input id="landing-email" type="email" name="email" value="" placeholder="Email Anda">
                        <button type="submit">Ikuti</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="landing-footer-bottom">
            <p>&copy; {{ now()->year }} Macau. Hak cipta dilindungi.</p>
            <div>
                <a href="#footer">Kebijakan Privasi</a>
                <a href="#footer">Syarat & Ketentuan</a>
            </div>
        </div>
    </div>
</footer>
