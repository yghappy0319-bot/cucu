'use strict';

// wow.js
new WOW().init();

const btn_menu = document.getElementById('btn_menu');
let link_menu = document.querySelectorAll('.link_menu');
const btn_close = document.getElementById('btn_close');
const wrap_gnb = document.querySelector('.wrap_gnb');
let bx_link = document.querySelectorAll('.bx_link');

// 메뉴창 열기
const openMenu = function() {
  wrap_gnb.classList.add('on');
}

// 메뉴창 닫기
const closeMenu = function() {
  wrap_gnb.classList.remove('on');
}

btn_menu.addEventListener('click', openMenu);
btn_close.addEventListener('click', closeMenu);

// 메뉴 클릭 후 0.5초 뒤 메뉴 창 닫힘
link_menu.forEach(function(e) {
  e.addEventListener('click', function() {
    setTimeout(closeMenu, 500);
  });
});

// 메뉴 스크롤
for (let i = 0; i < link_menu.length; i++) {
  link_menu[i].addEventListener('click', function(e) {
    e.preventDefault();
    bx_link[i].scrollIntoView({behavior: "smooth"});
  });
}

$(function() {
  let tabBtn = $('.tab_type1 > li > *');
  tabBtn.on('click', function(e) {
    e.preventDefault();
    $(this).parent('li').addClass('on');
  });
});

