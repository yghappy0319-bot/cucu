/**
 * 본방 메신저봇 — info1 호출 조건 수정용 스니펫
 *
 * 이 파일은 서버 PHP가 아니라, 안드로이드 메신저봇(채팅자동응답) 스크립트에
 * 붙여 넣는 예시입니다. 실제 변수명(room, msg, sender, url 등)은
 * 사용 중인 봇 스크립트에 맞게 바꿔 주세요.
 *
 * 핵심: `.` 명령뿐 아니라 `/도하` 일 때도 info1.php 를 호출
 */

// ===== 변경 전 (예시) =====
// if (msg.startsWith(".")) {
//   var res = Utils.getWebText(API + "/api/info1.php?nick=" + encodeURIComponent(nick) + "&msg=" + encodeURIComponent(msg));
//   ...
// }

// ===== 변경 후 =====
function shouldCallInfo1(rawMsg) {
  var t = String(rawMsg || "").trim();
  if (t.indexOf(".") === 0) return true;
  // /도하 · ／도하 (전각 슬래시)
  if (t === "/도하" || t === "／도하") return true;
  return false;
}

// 사용 예
// if (shouldCallInfo1(msg)) {
//   // ※ msg에 / 가 있으면 반드시 encodeURIComponent 할 것
//   var res = Utils.getWebText(
//     API + "/api/info1.php?nick=" + encodeURIComponent(nick) +
//     "&msg=" + encodeURIComponent(msg)
//   );
//   // res JSON 의 data 가 있으면 방 응답
// }
