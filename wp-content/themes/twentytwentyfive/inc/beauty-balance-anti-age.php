<?php
$bb_anti_age_copy = [
    'uk' => [
        'Догляд проти зморшок', 'Навіщо витрачати ще 20 хвилин на крем,', 'якщо подушку можна обрати один раз?',
        'Щовечора — ще один крем проти зморшок. Щоранку — знайомий залом на щоці. Якщо він з’являється після сну на боці, почніть із подушки. Це вибір, який ви робите один раз, і жодного зайвого кроку перед сном.',
        '«Ортопедична» тепер пишуть ледь не на кожній коробці. А де місце для щоки?',
        'Beauty Balance — сертифікована ортопедична подушка.',
        'Її форма підтримує голову й шию, а виїмка дає щоці більше простору й допомагає зменшити тиск на обличчя під час сну на боці.',
        'Сертифікати', 'Оберіть подушку один раз. Звільніть вечір для себе.', 'Обрати Beauty Balance',
    ],
    'ru' => [
        'Уход против морщин', 'Зачем тратить ещё 20 минут на крем,', 'если подушку можно выбрать один раз?',
        'Каждый вечер — ещё один крем против морщин. Каждое утро — знакомый залом на щеке. Если он появляется после сна на боку, начните с подушки. Её вы выбираете один раз, без лишних шагов перед сном.',
        '«Ортопедическая» теперь пишут чуть ли не на каждой коробке. А где место для щеки?',
        'Beauty Balance — сертифицированная ортопедическая подушка.',
        'Её форма поддерживает голову и шею, а выемка даёт щеке больше места и помогает уменьшить давление на лицо во время сна на боку.',
        'Сертификаты', 'Выберите подушку один раз. Освободите вечер для себя.', 'Выбрать Beauty Balance',
    ],
    'en' => [
        'Anti-wrinkle care', 'Why spend another 20 minutes on cream', 'when you can choose your pillow once?',
        'Another wrinkle cream at night. The same crease on your cheek in the morning. If it shows up after sleeping on your side, start with your pillow. Choose it once and skip adding another step to your bedtime routine.',
        '“Orthopaedic” is on practically every box now. But where does your cheek go?',
        'Beauty Balance is a certified orthopaedic pillow.',
        'Its shape supports your head and neck, while a cutout gives your cheek more room and helps reduce pressure on your face when you sleep on your side.',
        'Certificates', 'Choose your pillow once. Take back your evening.', 'Choose Beauty Balance',
    ],
    'pl' => [
        'Pielęgnacja przeciwzmarszczkowa', 'Po co poświęcać kolejne 20 minut na krem,', 'skoro poduszkę wybierasz raz?',
        'Wieczorem kolejny krem przeciwzmarszczkowy. Rano ta sama bruzda na policzku. Jeśli pojawia się po spaniu na boku, zacznij od poduszki. Wybierasz ją raz, bez dodatkowego kroku przed snem.',
        '„Ortopedyczna” widnieje dziś niemal na każdym pudełku. A gdzie miejsce na policzek?',
        'Beauty Balance to certyfikowana poduszka ortopedyczna.',
        'Jej kształt podpiera głowę i szyję, a wyprofilowane wgłębienie daje policzkowi więcej miejsca i pomaga zmniejszyć nacisk na twarz podczas snu na boku.',
        'Certyfikaty', 'Wybierz poduszkę raz. Zostaw wieczór dla siebie.', 'Wybierz Beauty Balance',
    ],
    'fr' => [
        'Soin antirides', 'Pourquoi passer encore 20 minutes à appliquer une crème', 'quand on peut choisir son oreiller une fois ?',
        'Le soir, une crème antirides de plus. Le matin, le même pli sur la joue. S’il apparaît après une nuit sur le côté, commencez par votre oreiller. Vous le choisissez une fois, sans geste supplémentaire avant de dormir.',
        '« Orthopédique » s’affiche sur presque toutes les boîtes. Et la place pour la joue ?',
        'Beauty Balance est un oreiller orthopédique certifié.',
        'Sa forme soutient la tête et la nuque. Son évidement laisse plus de place à la joue et aide à réduire la pression sur le visage quand vous dormez sur le côté.',
        'Certificats', 'Choisissez votre oreiller une fois. Gardez vos soirées pour vous.', 'Choisir Beauty Balance',
    ],
    'de' => [
        'Pflege gegen Falten', 'Warum noch 20 Minuten mit Eincremen verbringen,', 'wenn Sie Ihr Kissen nur einmal auswählen?',
        'Abends noch eine Creme gegen Falten. Morgens dieselbe Knitterfalte auf der Wange. Wenn sie nach dem Schlafen auf der Seite auftaucht, fangen Sie beim Kissen an. Das wählen Sie einmal aus, ganz ohne zusätzlichen Schritt vor dem Schlafengehen.',
        '„Orthopädisch“ steht heute auf fast jeder Packung. Doch wo bleibt Platz für die Wange?',
        'Beauty Balance ist ein zertifiziertes orthopädisches Kissen.',
        'Seine Form stützt Kopf und Nacken. Die Aussparung lässt der Wange mehr Platz und hilft, den Druck aufs Gesicht beim Schlafen auf der Seite zu verringern.',
        'Zertifikate', 'Wählen Sie Ihr Kissen einmal. Genießen Sie Ihren Abend.', 'Beauty Balance wählen',
    ],
];
$bb_language = function_exists('pll_current_language') ? pll_current_language() : 'uk';
[$label, $title, $accent, $intro, $question, $claim, $details, $certificates, $closer, $cta] = $bb_anti_age_copy[$bb_language] ?? $bb_anti_age_copy['uk'];
$bb_cert_page = get_page_by_path('patenty-ta-sertyfikaty');
$bb_cert_id = $bb_cert_page ? $bb_cert_page->ID : 0;
if ($bb_cert_id && function_exists('pll_get_post')) {
    $bb_cert_id = pll_get_post($bb_cert_id, $bb_language) ?: $bb_cert_id;
}
?>
<section class="bb-anti-age" aria-labelledby="bb-anti-age-title">
    <p class="bb-anti-age__label">Anti-Age · <?php echo esc_html($label); ?></p>
    <h1 id="bb-anti-age-title"><?php echo esc_html($title); ?> <em><?php echo esc_html($accent); ?></em></h1>
    <p><?php echo esc_html($intro); ?></p>
    <hr>
    <h2><?php echo esc_html($question); ?></h2>
    <div>
        <p><strong><?php echo esc_html($claim); ?></strong> <?php echo esc_html($details); ?></p>
        <?php if ($bb_cert_id) : ?>
            <a class="bb-anti-age__cert" href="<?php echo esc_url(get_permalink($bb_cert_id)); ?>"><?php echo esc_html($certificates); ?> <span aria-hidden="true">↗</span></a>
        <?php endif; ?>
    </div>
    <p class="bb-anti-age__closer"><?php echo esc_html($closer); ?></p>
    <a class="bb-anti-age__cta" href="#products-catalog"><?php echo esc_html($cta); ?> <span aria-hidden="true">↓</span></a>
</section>
