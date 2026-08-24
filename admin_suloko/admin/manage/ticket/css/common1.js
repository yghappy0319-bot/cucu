function bottomScroll(){
	var bottomSec = $(".main-app-box").offset().top;
	$("html, body").stop().animate({scrollTop:bottomSec});
}

function qaToggle(obj){
	var parents = $(obj).closest(".table-box");
	var reply = $(obj).next("tr.reply");

	$(obj).toggleClass("active");
	$(obj).siblings().removeClass("active");
	reply.toggle();
	parents.find("tr.reply").not(reply).hide();
}

function toTop(){
	$("html, body").stop().animate({scrollTop:300});
}

function toggle(obj){
	$(obj).toggleClass("active");
}

function popupOpen(type){
	var box = $(".popup-box[data-name=" + type + "]");

	$("html , body").addClass("fixed");
	box.addClass("fixed");
}


function openPopup1() {
	var popupWindow1 = window.open('popup1.html', 'popupWindow1', 'width=400,height=791');
}

function openPopup2() {
	var popupWindow2 = window.open('popup2.html', 'popupWindow2', 'width=400,height=791');
}




function installOpen(){
	$(".main-app-box .app-popup").addClass("fixed");
}

function installClose(){
	$(".main-app-box .app-popup").removeClass("fixed");
}

function videoOpen(obj){
	var videoSrc = "https://www.youtube.com/embed/";
	var videoLink = $(obj).attr("data-src");
	var all = videoSrc + videoLink;

	$(".video-popup-box iframe").attr("src" , all);
	$(".video-popup-box").addClass("fixed");
}

function videoClose(){
	$(".video-popup-box iframe").attr("src" , "");
	$(".video-popup-box").removeClass("fixed");
}

$(document).on("click", ".popup-box.fixed", function(e){
	var len = $(".popup-box.fixed").length;

	if(len  < 2){
		$("html , body").removeClass("fixed");
	}else{
		$("html , body").addClass("fixed");
	}
	if($(e.target).hasClass("fixed")) {
		$(this).removeClass("fixed");
	}
});

// 팝업 닫기
function popupClose(type){
	// 특정 팝업을 재호출한다면
	var box = $(".popup-box[data-name=" + type + "]");
	var len = $(".popup-box.fixed").length;

	if(len  < 2){
		$("html , body").removeClass("fixed");
	}
	box.removeClass("fixed");
}

function menuOpen(){
	$("html , body , header").addClass("fixed");
}

function menuClose(){
	$("html , body , header").removeClass("fixed");
}

// 외부영역 클릭 시 특정요소 닫기
$(document).mouseup(function (e){
	var menuBox = $(".header-mobile");
	var dateBox = $(".date-box .date .inner");
	var videoBox = $(".video-popup-box");
	if(menuBox.has(e.target).length === 0){
		$("html , body , header").removeClass("fixed");
	}
	if(dateBox.has(e.target).length === 0){
		$("html , body, .date-box .date").removeClass("fixed");
	}
	if(videoBox.has(e.target).length === 0){
		$(".video-popup-box iframe").attr("src" , "");
		$(".video-popup-box").removeClass("fixed");
	}
});

/* 셀렉박스 옵션선택 포커스 */
$(document).on("change",".select-box select",function(){
	var box = $(this).closest(".select-box");
	var opt = $(this).find("option:selected").val();
	var redex = /\s/ig;
	var value = opt.toString().replace(redex, "").length;

	// 띄어쓰기를 제외한 글자가 존재하다면
	if(value > 0){
		box.addClass("active");
	}else{
		box.removeClass("active");
	}
});

/* 체크박스 전체동의 */
$(document).on("change",".check-total-box .check-total input",function(){
	var type = $(this).attr("data-name");
	$(".check-total-box .check-list input").prop("checked" , $(this).prop("checked"));
});

$(document).on("change",".check-total-box .check-list input",function(){
	var len = $(".check-total-box .check-list input").length;
	var checklen = $(".check-total-box .check-list input:checked").length;

	if(len > checklen) $(".check-total-box .check-total input").prop("checked",false);
	if(len == checklen) $(".check-total-box .check-total input").prop("checked",true);
});

/* 데이트박스 */
$(document).on("click",".date-box button",function(){
	var parents = $(this).closest(".date");

	$(".date-box .date").not(parents).removeClass("fixed");
	$("html , body").addClass("fixed");
	parents.addClass("fixed");
});

/* 게임수 / 게임구분 */
$(document).on("click",".game-category a",function(){
	var parents = $(this).closest(".game-category");
	var li = $(this).closest("li");

	li.addClass("active");
	li.siblings().removeClass("active");
});

$(document).on("click",".mypage-cate-box a",function(){
	var parents = $(this).closest(".mypage-cate-box");
	var li = $(this).closest("li");

	li.addClass("active");
	li.siblings().removeClass("active");
});

$(window).scroll(function(){
	var scl = $(this).scrollTop();

	if(scl > 0){
		$(".header-side").addClass("active");
	}else{
		$(".header-side").removeClass("active");
	}
});

$(document).on("click", ".board-faq-box button", function(){
	var parents = $(this).closest("li");

	parents.toggleClass("active");
	parents.siblings().removeClass("active");
});


document.addEventListener('DOMContentLoaded', function() {
	const lottoNumbers = document.querySelectorAll('.popup-lotto .lotto-box .lotto-number ul li b');
	const imagePath = '/admin/assets/images/';
	const imageSize = '14px'; // 이미지 크기 조절

	lottoNumbers.forEach(b => {
		const numberText = b.textContent;
		if (numberText.length === 2) {
			const firstDigit = numberText.charAt(0);
			const secondDigit = numberText.charAt(1);

			const firstImage = document.createElement('img');
			firstImage.src = `${imagePath}number_${firstDigit}.png`;
			firstImage.alt = firstDigit;
			firstImage.style.width = imageSize;
			firstImage.style.height = imageSize;

			const secondImage = document.createElement('img');
			secondImage.src = `${imagePath}number_${secondDigit}.png`;
			secondImage.alt = secondDigit;
			secondImage.style.width = imageSize;
			secondImage.style.height = imageSize;

			b.textContent = ''; // 기존 텍스트를 지우기
			b.appendChild(firstImage);
			b.appendChild(secondImage);
		}
	});
});
