(function (global) {
  function initOrderDeadlineRefresh(orderEndMs) {
    if (!orderEndMs) return;

    var reloaded = false;

    function reloadIfPastDeadline() {
      if (reloaded) return;
      if (Date.now() >= orderEndMs) {
        reloaded = true;
        alert("주문 마감되어 페이지를 갱신합니다.");
        global.location.reload();
      }
    }

    reloadIfPastDeadline();

    var timerId = global.setInterval(function () {
      reloadIfPastDeadline();
      if (reloaded) global.clearInterval(timerId);
    }, 1000);

    document.addEventListener("visibilitychange", function () {
      if (!document.hidden) reloadIfPastDeadline();
    });
  }

  global.initOrderDeadlineRefresh = initOrderDeadlineRefresh;
})(window);
