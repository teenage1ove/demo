(function () {
    var slider = document.querySelector('.slider');
    if (!slider) return;

    var track = slider.querySelector('.slider__track');
    var slides = slider.querySelectorAll('.slider__slide');
    var dots = slider.querySelectorAll('.slider__dot');
    var index = 0;
    var total = slides.length;

    function go(i) {
        index = (i + total) % total;
        track.style.transform = 'translateX(-' + (index * 100) + '%)';
        dots.forEach(function (dot, d) {
            dot.classList.toggle('is-active', d === index);
        });
    }

    slider.querySelector('.slider__btn--next').addEventListener('click', function () {
        go(index + 1);
        restart();
    });
    slider.querySelector('.slider__btn--prev').addEventListener('click', function () {
        go(index - 1);
        restart();
    });

    var timer = setInterval(function () { go(index + 1); }, 3000);
    function restart() {
        clearInterval(timer);
        timer = setInterval(function () { go(index + 1); }, 3000);
    }

    go(0);
})();
