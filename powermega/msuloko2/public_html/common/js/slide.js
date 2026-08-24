$(function() {
  const swiper1 = new Swiper('.sec_visual .swiper-container', {
    spaceBetween: 30,
    centeredSlides: true,
    loop: true,
    loopFillGroupWithBlank: true,
    autoplay: {
      delay: 10000,
      disableOnInteraction: false,
    },
    pagination: {
      el: '.swiper-pagination',
      clickable: true,
    },
  });

  const swiper2 = new Swiper('.sec_event .swiper-container', {
    observer: true,
    observeParents: true,
    spaceBetween: 30,
    centeredSlides: true,
    loop: true,
    autoplay: {
      delay: 10000,
      disableOnInteraction: false,
    },
    pagination: {
      el: '.sec_event .swiper-pagination',
      clickable: true
    },
    navigation: {
      nextEl: '.swiper-button-next',
      prevEl: '.swiper-button-prev',
    },
  });
});
