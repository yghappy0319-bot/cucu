$(document).ready(function(){

    $(".new_all_menu_btn").click(function(){
        $(".new_all_menu").addClass("on")
        $(".new_all_menu .close").click(function(){
            $(".new_all_menu").removeClass("on")
        })
    });

    $(".scroll_top").click(function(){
        $("html, body").stop().animate({scrollTop:0},300)
        return false;
    })

    var new_main_popup_m = new Swiper ('.new_main_popup_m .rolling', {
        direction: 'horizontal',
        slidesPerView: 1,
        spaceBetween: 0,
        grabCursor : true,
        loop: true,
        speed: 500,
        autoplay:{
            delay: 2000,
        },
        pagination: {
            el: '.new_main_popup_m_pagination',
            type: 'fraction',
        },
    });

    var new_main_popup = new Swiper ('.new_main_popup .rolling', {
        direction: 'horizontal',
        slidesPerView: 1,
        spaceBetween: 0,
        grabCursor : true,
        loop: true,
        speed: 500,
        autoplay:{
            delay: 2000,
        },
        navigation: {
            nextEl: '.banner_btn_next',
            prevEl: '.banner_btn_prev',
        },
        breakpoints: {
            769: {
                centeredSlides: false,
                slidesPerView: 2,
                spaceBetween: 0,
            },
            1023: {
                centeredSlides: false,
                slidesPerView: 2,
                spaceBetween: 0,
            },
        },
    });
    // ✅ 쿠키 삭제 함수
    function deleteCookie(name) {
      document.cookie = name + "=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
    }

    $(".new_main_popup .btn").click(function(){
    	if($(this).parent(".new_main_popup").hasClass("on")){
    		$(".new_main_popup").removeClass("on")
        $(this).find("i").attr("class","xi-angle-right")
        $(this).find("i").removeClass('vertical-text');
        $(this).find("i").text("");
        console.log('닫기');
            //deleteCookie("main_pop");
            setCookie("main_pop", 1, 2);
    	}else{
        console.log('열기');
        deleteCookie("main_pop");
    		$(".new_main_popup").addClass("on");
        $(this).find("i").attr("class","xi-close")
        $(this).find("i").addClass('vertical-text');
        $(this).find("i").text("팝업닫기");
    	}
    })

    var new_main_banner = new Swiper ('.new_main_banner .rolling', {
        direction: 'horizontal',
        spaceBetween: 10,
        slidesPerView: 3,
        centeredSlides: true,
        grabCursor : true,
        loop: true,
        speed: 500,
        autoplay:{
            delay: 3000,
        },
        /*navigation: {
            nextEl: '.btn_next',
            prevEl: '.btn_prev',
        },*/
        breakpoints: {
            769: {
                direction: 'horizontal',
                spaceBetween: 10,
                slidesPerView: 2,
                centeredSlides: false,
            },
            1023: {
                direction: 'vertical',
                spaceBetween: 10,
                slidesPerView: "auto",
                centeredSlides: false,
            },
        },
    });

    var new_main_winning = new Swiper ('.new_main_winning .rolling', {
        direction: 'horizontal',
        spaceBetween: 10,
        slidesPerView: 3,
        centeredSlides: true,
        grabCursor : true,
        loop: true,
        speed: 500,
        autoplay:{
            delay: 4000,
        },
        navigation: {
            nextEl: '.btn_next',
            prevEl: '.btn_prev',
        },
        breakpoints: {
            769: {
                spaceBetween: 20,
                slidesPerView: 2,
                centeredSlides: false,
            },
            1023: {
                slidesPerView: 3,
                spaceBetween: 20,
                centeredSlides: false,
            },
        },
    });

    $(".new_main_winning .menu p:eq(0)").click(function(){
        $(".new_main_winning .menu p").removeClass("on");
        $(".new_main_winning .menu p:eq(0)").addClass("on");
        $(".new_main_winning .mega").show();
        $(".new_main_winning .power").show();
    });

    $(".new_main_winning .menu p:eq(1)").click(function(){
        $(".new_main_winning .menu p").removeClass("on");
        $(".new_main_winning .menu p:eq(1)").addClass("on");
        $(".new_main_winning .mega").show();
        $(".new_main_winning .power").css("display","none");
    });

    $(".new_main_winning .menu p:eq(2)").click(function(){
        $(".new_main_winning .menu p").removeClass("on");
        $(".new_main_winning .menu p:eq(2)").addClass("on");
        $(".new_main_winning .mega").css("display","none");
        $(".new_main_winning .power").show();
    });

    $(".new_main_guide .menu p:eq(0)").click(function(){
        $(".new_main_guide .menu p").removeClass("on")
        $(".new_main_guide .menu p:eq(0)").addClass("on")
        $(".new_main_guide .guide_buy").addClass("on")
        $(".new_main_guide .guide_ok").removeClass("on")
    });

    $(".new_main_guide .menu p:eq(1)").click(function(){
        $(".new_main_guide .menu p").removeClass("on")
        $(".new_main_guide .menu p:eq(1)").addClass("on")
        $(".new_main_guide .guide_buy").removeClass("on")
        $(".new_main_guide .guide_ok").addClass("on")
    });

    $(".new_popup_my_save_ball .close").click(function(){
        $(".new_popup_my_save_ball").removeClass("on");
    });


	// var scroll_header = 0
	// $(window).scroll(function(){
	// 	if($(window).scrollTop() > 1){
	// 		if(scroll_header == 1) return;
	// 		scroll_header=1
	// 		$(".header").addClass("on")
	// 	}else{
	// 		scroll_header=0
	// 		$(".header").removeClass("on")
	// 	}
	// })
	//

	//
	//
	// $(".all_menu_btn").click(function(){
	// 	$(".all_menu").addClass("on")
	// 	$(".all_menu .close_btn, .all_menu .close").click(function(){
	// 		$(".all_menu").removeClass("on")
	// 	})
	// })
	// $(".all_menu .row .fl .menu > ul > li").click(function(){
	// 	if($(this).hasClass("hover")){
	// 		$(this).removeClass("hover")
	// 		$(".all_menu .row .fl .menu > ul > li ul").slideUp(300)
	// 	}else{
	// 		$(".all_menu .row .fl .menu > ul > li").removeClass("hover")
	// 		$(this).addClass("hover")
	// 		$(".all_menu .row .fl .menu > ul > li ul").slideUp(300)
	// 		$("ul",this).slideDown(200)
	// 	}
	// })
	//
	//
	// // header
	// var header_search = new Swiper ('.header .middle .search .hot .rolling', {
	// 	direction: 'vertical',
	// 	slidesPerView: 1,
	// 	grabCursor : false,
	// 	loop: true,
	// 	speed: 500,
	// 	autoplay:{
	// 		delay: 1000,
	// 	},
	// });
	//
	// var header_banner = new Swiper ('.header .middle .banner', {
	// 	direction: 'horizontal',
	// 	slidesPerView: 1,
	// 	grabCursor : false,
	// 	loop: true,
	// 	speed: 500,
	// 	autoplay:{
	// 		delay: 1000,
	// 	},
	// });
	//
	//
	// // visual
	// var home_visual = new Swiper ('.home_visual', {
	// 	direction: 'horizontal',
	// 	slidesPerView: 1,
	// 	grabCursor : false,
	// 	loop: true,
	// 	speed: 500,
	// 	autoplay:{
	// 		delay: 5000,
	// 	},
	// 	navigation: {
	// 		nextEl: '.swiper-button-next',
	// 		prevEl: '.swiper-button-prev',
	// 	},
	// 	pagination: {
	// 		el: '.swiper-pagination',
	// 		clickable: true,
	// 		renderBullet: function (index, className) {
	// 			return '<span class="' + className + '">' + (index + 1) + '</span>';
	// 		},
	// 		renderBullet: function (index, className) {
	// 			 switch(index){
	// 				 case 0:text='에메랄드빛';break;
	// 				 case 1:text='천국같은 휴가';break;
	// 				 case 2:text='자연이 선사한 선물';break;
	// 				 case 3:text='여유로은 휴식을 위해';break;
	// 			 }
	// 			 return '<span class="' + className + '">' + text + '</span>';
	// 			 },
	// 	},
	// 	runCallbacksOnInit : true,
	// 	on:{
	// 		slideChange: function () {
	// 			$(".home_visual .swiper-wrapper .swiper-slide .row").addClass("on")
	// 		},
	// 		slideChangeTransitionStart:function(){
	// 			$(".home_visual .swiper-wrapper .swiper-slide .row").removeClass("on")
	// 		},
	// 		slideChangeTransitionEnd:function(){
	// 			$(".home_visual .swiper-wrapper .swiper-slide .row").addClass("on")
	// 		},
	// 	},
	// });
	//
	//
	// // home search
	// $(".home_search_in_btn").click(function(){
	// 	$(".home_search .fl > .row > .box2").removeClass("on")
	// 	$(".home_search_in").addClass("on")
	// })
	//
	// $(".home_search_date_btn").click(function(){
	// 	$(".home_search .fl > .row > .box2").removeClass("on")
	// 	$(".home_search_date").addClass("on")
	// })
	// $(".home_search_date .row .body .box ul li.on").click(function(){
	// 	$(this).addClass("click")
	// })
	//
	// $(".home_search_user_btn").click(function(){
	// 	$(".home_search .fl > .row > .box2").removeClass("on")
	// 	$(".home_search_user").addClass("on")
	// })
	//
	// $(".home_search .fl > .row .box2 .close").click(function(){
	// 	$(".home_search .fl > .row .box2").removeClass("on")
	// })
	// $(".home_search .fl > .row > .box2 .xi-close").click(function(){
	// 	$(".home_search .fl > .row .box2").removeClass("on")
	// })
	//
	//
	// // ticket search
	// $(".ticket_search_in_btn").click(function(){
	// 	$(".ticket_search .fl > .row > .box2").removeClass("on")
	// 	$(".ticket_search_in").addClass("on")
	// })
	//
	// $(".ticket_search_date_btn").click(function(){
	// 	$(".ticket_search .fl > .row > .box2").removeClass("on")
	// 	$(".ticket_search_date").addClass("on")
	// })
	// $(".ticket_search_date .row .body .box ul li.on").click(function(){
	// 	$(this).addClass("click")
	// })
	//
	// $(".ticket_search_user_btn").click(function(){
	// 	$(".ticket_search .fl > .row > .box2").removeClass("on")
	// 	$(".ticket_search_user").addClass("on")
	// })
	//
	// $(".ticket_search .fl > .row .box2 .close").click(function(){
	// 	$(".ticket_search .fl > .row .box2").removeClass("on")
	// })
	// $(".ticket_search .fl > .row > .box2 .xi-close").click(function(){
	// 	$(".ticket_search .fl > .row .box2").removeClass("on")
	// })
	//
	//
	// // home hot
	// var home_hot = new Swiper ('.home_hot .rolling', {
	// 	direction: 'horizontal',
	// 	centeredSlides: true,
	// 	slidesPerView: 2,
	// 	spaceBetween: 10,
	// 	grabCursor : false,
	// 	loop: true,
	// 	speed: 500,
	// 	autoplay:{
	// 		delay: 4000,
	// 	},
	// 	navigation: {
	// 		nextEl: '.swiper-button-next',
	// 		prevEl: '.swiper-button-prev',
	// 	},
	// 	scrollbar: {
	// 		el: '.swiper-scrollbar',
	// 	},
	// 	breakpoints: {
	// 		769: {
	// 			centeredSlides: false,
	// 			slidesPerView: 2,
	// 			spaceBetween: 20,
	// 		},
	// 		1023: {
	// 			centeredSlides: false,
	// 			slidesPerView: 3,
	// 			spaceBetween: 20,
	// 		},
	// 	},
	// });
	// $(".home_hot .btn-prev").click(function(){
	// 	$(".home_hot .swiper-button-prev").click()
	// })
	// $(".home_hot .btn-next").click(function(){
	// 	$(".home_hot .swiper-button-next").click()
	// })
	//
	//
	// // detail search
	// $(".hotel_detail_search_date_btn").click(function(){
	// 	$(".hotel_detail_search_date").addClass("on")
	// })
	// $(".hotel_detail_search_date .row .body .box ul li.on").click(function(){
	// 	$(this).addClass("click")
	// })
	//
	// $(".hotel_detail_search_user_btn").click(function(){
	// 	$(".hotel_detail_search_user").addClass("on")
	// })
	//
	// $(".hotel_detail_search > .body > .row .box2 > .close").click(function(){
	// 	$(".hotel_detail_search > .body > .row .box2").removeClass("on")
	// })
	// $(".hotel_detail_search > .body > .row .box2 > .row > i").click(function(){
	// 	$(".hotel_detail_search > .body > .row .box2").removeClass("on")
	// })
	//
	//
	// // ticket search
	// $(".ticket_detail_search_date_btn").click(function(){
	// 	$(".ticket_detail_search_date").addClass("on")
	// })
	// $(".ticket_detail_search_date .row .body .box ul li.on").click(function(){
	// 	$(this).addClass("click")
	// })
	//
	// $(".ticket_detail_search_user_btn").click(function(){
	// 	$(".ticket_detail_search_user").addClass("on")
	// })
	//
	// $(".ticket_detail_search > .body > .row .box2 > .close").click(function(){
	// 	$(".ticket_detail_search > .body > .row .box2").removeClass("on")
	// })
	// $(".ticket_detail_search > .body > .row .box2 > .row > i").click(function(){
	// 	$(".ticket_detail_search > .body > .row .box2").removeClass("on")
	// })
	//
	//
	// // detail rolling
	// var rolling_min = new Swiper('.hotel_detail_head .rolling .min', {
	// 	spaceBetween: 8,
	// 	slidesPerView: 5,
	// 	freeMode: true,
	// 	watchSlidesProgress: true,
	// });
	// var rolling_max = new Swiper('.hotel_detail_head .rolling .max', {
	// 	loop: true,
	// 	navigation: {
	// 		nextEl: ".swiper-button-next",
	// 		prevEl: ".swiper-button-prev",
	// 	},
	// 	thumbs: {
	// 		swiper: rolling_min,
	// 	},
	// });
	//
	//
	// var sale_min = new Swiper('.sale_detail_head .rolling .min', {
	// 	spaceBetween: 8,
	// 	slidesPerView: 5,
	// 	freeMode: true,
	// 	watchSlidesProgress: true,
	// });
	// var sale_max = new Swiper('.sale_detail_head .rolling .max', {
	// 	loop: true,
	// 	navigation: {
	// 		nextEl: ".swiper-button-next",
	// 		prevEl: ".swiper-button-prev",
	// 	},
	// 	thumbs: {
	// 		swiper: sale_min,
	// 	},
	// });
	//
	//
	// // detail btn
	// $(".btn_heart").click(function(){
	// 	$(this).toggleClass("on")
	// })
	//
	//
	// // detail popup
	// var hotel_detail_room_popup = new Swiper('.hotel_detail_room_popup .row .img .rolling', {
	// 	loop: true,
	// 	navigation: {
	// 		nextEl: ".swiper-button-next",
	// 		prevEl: ".swiper-button-prev",
	// 	},
	// });
	// $(".hotel_detail_room_popup_btn").click(function(){
	// 	$(".hotel_detail_room_popup").addClass("on")
	// })
	// $(".hotel_detail_room_popup .close, .hotel_detail_room_popup .row > i").click(function(){
	// 	$(".hotel_detail_room_popup").removeClass("on")
	// })
	//
	//
	// var hotel_detail_room_img_popup = new Swiper('.hotel_detail_room_img_popup .row .img .rolling', {
	// 	loop: true,
	// 	navigation: {
	// 		nextEl: ".swiper-button-next",
	// 		prevEl: ".swiper-button-prev",
	// 	},
	// });
	// $(".hotel_detail_room_img_popup_btn").click(function(){
	// 	$(".hotel_detail_room_img_popup").addClass("on")
	// })
	// $(".hotel_detail_room_img_popup .close, .hotel_detail_room_img_popup .row > i").click(function(){
	// 	$(".hotel_detail_room_img_popup").removeClass("on")
	// })
	//
	//
	// // detail sale popup
	// var sale_detail_room_popup = new Swiper('.sale_detail_room_popup .row .img .rolling', {
	// 	loop: true,
	// 	navigation: {
	// 		nextEl: ".swiper-button-next",
	// 		prevEl: ".swiper-button-prev",
	// 	},
	// });
	// $(".sale_detail_room_popup_btn").click(function(){
	// 	$(".sale_detail_room_popup").addClass("on")
	// })
	//
	// $(".sale_detail_room_popup .close, .sale_detail_room_popup .row > i").click(function(){
	// 	$(".sale_detail_room_popup").removeClass("on")
	// })
	//
	// var sale_detail_room_img_popup = new Swiper('.sale_detail_room_img_popup .row .img .rolling', {
	// 	loop: true,
	// 	navigation: {
	// 		nextEl: ".swiper-button-next",
	// 		prevEl: ".swiper-button-prev",
	// 	},
	// });
	// $(".sale_detail_room_img_popup_btn").click(function(){
	// 	$(".sale_detail_room_img_popup").addClass("on")
	// })
	// $(".sale_detail_room_img_popup .close, .sale_detail_room_img_popup .row > i").click(function(){
	// 	$(".sale_detail_room_img_popup").removeClass("on")
	// })
	//
	//
	// // detail calendar
	// $(".hotel_detail_calendar .type .list ul li").click(function(){
	// 	if($(this).hasClass("on")){
	// 		$(this).removeClass("on")
	// 		$(".hotel_detail_calendar .type .list ul li .body").slideUp(300)
	// 	}else{
	// 		$(".hotel_detail_calendar .type .list ul li").removeClass("on")
	// 		$(this).addClass("on")
	// 		$(".hotel_detail_calendar .type .list ul li .body").slideUp(300)
	// 		$(".body",this).slideDown(200)
	// 	}
	// })
	//
	// $(".hotel_detail_head .button .btn_calendar").click(function(){
	// 	if($(this).hasClass("on")){
	// 		$(this).removeClass("on")
	// 		$("p",this).html("달력으로 보기")
	// 		$(".reservation_type_calendar").removeClass("on")
	// 		$(".reservation_type_list").addClass("on")
	// 	}else{
	// 		$(this).addClass("on")
	// 		$("p",this).html("리스트로 보기")
	// 		$(".reservation_type_list").removeClass("on")
	// 		$(".reservation_type_calendar").addClass("on")
	// 	}
	// })
	//
	//
	// // detail sale calendar
	// $(".sale_detail_calendar .type .list ul li .head").click(function(){
	// 	if($(this).parents("li").hasClass("on")){
	// 		$(this).parents("li").removeClass("on")
	// 		$(".sale_detail_calendar .type .list ul li .body").slideUp(300)
	// 	}else{
	// 		$(".sale_detail_calendar .type .list ul li").removeClass("on")
	// 		$(this).parents("li").addClass("on")
	// 		$(".sale_detail_calendar .type .list ul li .body").slideUp(300)
	// 		$(this).parents("li").find(".body").slideDown(200)
	// 	}
	// })
	//
	//
	// // login
	// var member_login = new Swiper ('.member_login .rolling', {
	// 	direction: 'horizontal',
	// 	slidesPerView: 1,
	// 	spaceBetween: 0,
	// 	loop: true,
	// 	speed: 500,
	// 	autoplay:{
	// 		delay: 2000,
	// 	},
	// 	navigation: {
	// 		nextEl: '.swiper-button-next',
	// 		prevEl: '.swiper-button-prev',
	// 	},
	// });
	// $(".member_login .banner .btn-prev").click(function(){
	// 	$(".member_login .rolling .swiper-button-prev").click()
	// })
	// $(".member_login .banner .btn-next").click(function(){
	// 	$(".member_login .rolling .swiper-button-next").click()
	// })
	//
	//
	//
	//
	//
	// // 팝업
	// $(".popup_privacy_btn").click(function(){
	// 	$(".popup_privacy").addClass("on")
	// 	$(".popup_privacy .close, .popup_privacy .xi-close, .popup_privacy .btn").click(function(){
	// 		$(".popup_privacy").removeClass("on")
	// 	})
	// })
	// $(".popup_mail_btn").click(function(){
	// 	$(".popup_mail").addClass("on")
	// 	$(".popup_mail .close, .popup_mail .xi-close, .popup_mail .btn").click(function(){
	// 		$(".popup_mail").removeClass("on")
	// 	})
	// })
	// $(".popup_contact_btn").click(function(){
	// 	$(".popup_contact").addClass("on")
	// 	$(".popup_contact .close, .popup_contact .xi-close, .popup_contact .box .btn a:eq(0)").click(function(){
	// 		$(".popup_contact").removeClass("on")
	// 	})
	// })
	// $(".type_write .check .btn").click(function(){
	// 	$(this).toggleClass("on")
	// })
	//
	//
	// // type
	// $(".type_info .sort .btn a").click(function(){
	// 	$(".type_info .sort .btn a").removeClass("on")
	// 	$(this).addClass("on")
	// })
	//
	// $(".type_faq ul li").click(function(){
	// 	if($(this).hasClass("on")){
	// 		$(this).removeClass("on")
	// 		$(".type_faq ul li .a").slideUp(300)
	// 	}else{
	// 		$(".type_faq ul li").removeClass("on")
	// 		$(this).addClass("on")
	// 		$(".type_faq ul li .a").slideUp(300)
	// 		$(".a",this).slideDown(200)
	// 	}
	// })



})
