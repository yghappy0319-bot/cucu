(function (global) {
  var DEADLINE_REFRESH_MSG = '주문 마감되어 페이지를 갱신합니다.';

  function showMessageAndReload() {
    if (typeof global.alert === 'function') {
      global.alert(DEADLINE_REFRESH_MSG);
      var okBtn = document.getElementById('dialog_ok');
      var dialogs = document.getElementById('dialogs');
      if (okBtn) {
        okBtn.onclick = function () {
          if (dialogs) dialogs.style.display = 'none';
          global.location.reload();
        };
      } else {
        global.location.reload();
      }
    } else {
      global.alert(DEADLINE_REFRESH_MSG);
      global.location.reload();
    }
  }

  function initOrderDeadlineRefresh(orderEndMs) {
    if (!orderEndMs) return;

    var reloaded = false;

    function reloadIfPastDeadline() {
      if (reloaded) return;
      if (Date.now() >= orderEndMs) {
        reloaded = true;
        showMessageAndReload();
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
