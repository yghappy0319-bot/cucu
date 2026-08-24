var swiper = new Swiper(".main-list-box .swiper-container", {
	loop: true,
	centeredSlides: true,
	slidesPerView: "auto",
	spaceBetween: 24,
	allowTouchMove : false,
	speed: 2500,
	autoplay: {
		delay: 0,
		stopOnLastSlide: false,
		disableOnInteraction: true,
	},
	breakpoints: {
		768: {
			spaceBetween: 10,
		},
	},
	/*
	navigation: {
		nextEl: ".itemSwiper .swiper-button-next",
        prevEl: ".itemSwiper .swiper-button-prev",
    },
	*/
});

$(".main-list-box .swiper-container").each(function(elem, target){
    var swp = target.swiper;
    $(this).hover(function() {
        swp.autoplay.stop();
    }, function() {
        swp.autoplay.start();
    });
});


var swiper2 = new Swiper(".main-swiper-box .swiper-container", {
	loop: true,
	centeredSlides: true,
	slidesPerView: 1,
	spaceBetween: 0,
	speed: 500,
	fade: true,
	autoplay: {
        delay: 5000,
        disableOnInteraction: false,
    },
	/*
	navigation: {
		nextEl: ".itemSwiper .swiper-button-next",
        prevEl: ".itemSwiper .swiper-button-prev",
    },
	*/
});

var swiper3 = new Swiper(".main-board-box .swiper-container", {
	loop: true,
	centeredSlides: true,
	slidesPerView: 1,
	spaceBetween: 0,
	direction: "vertical",
	speed: 500,
	fade: true,
	autoplay: {
        delay: 2500,
        disableOnInteraction: false,
    },
});
