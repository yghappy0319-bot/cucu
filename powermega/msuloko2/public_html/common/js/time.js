
/**
 * Created by Hack on 2016/11/8.
 */
let minute;
let second;
let msec;
let time_length;
jQuery.fn.extend({
  plug_in_time : function(arr){
    $(this).html(load_time2(arr));
    minute = $('.minute');
    second = $('.second');
    msec = $('.msec');
    time_length = minute.length;
    time();
  }
})

function load_time2(arr){
  arr['minute'] == 0 ? arr['minute'] = '00' : arr['minute'] = arr['minute'];
  arr['second'] == 0 ? arr['second'] = '00' : arr['second'] = arr['second'];
  arr['msec'] == 0 ? arr['msec'] = '00' : arr['msec'] = arr['msec'];
  let time_str = '<span class="minute">' + arr['minute'] + '</span>:<span class="second">' + arr['second'] + '</span><span class="msec" style="display:none;">' + arr['msec'] + '</span>';
  return time_str;
}

function time() {
  for (let i = 0; i < time_length; i++) {
    if (minute.eq(i).text() == '00' && second.eq(i).text() == '00' && msec.eq(i).text() == '00') {
      clearTimeout(mytime);
			$('.count_down').html('03:00');
			$('.auth_btn').html('재발송');
			$('.auth_btn').show();
			return false;
    } else {
      if (minute.eq(i).text() != '00' && second.eq(i).text() == '00') {
        let num_m = minute.eq(i).text() - 1;
        if (parseInt(minute.eq(i).text()) < 11) {
          minute.eq(i).text('0' + num_m);
        } else {
          minute.eq(i).text(num_m);
        }
        second.eq(i).text('60');
      } else {
        if (msec.eq(i).text() == '00') {
          let num_s = second.eq(i).text() - 1;
          if (parseInt(second.eq(i).text()) < 11) {
            second.eq(i).text('0' + num_s);
          } else {
            second.eq(i).text(num_s);
          }
          msec.eq(i).text('99');
        } else {
          let num_c = msec.eq(i).text() - 1;
          if (parseInt(msec.eq(i).text()) < 11) {
            msec.eq(i).text('0' + num_c);
          } else {
            msec.eq(i).text(num_c);
          }
        }
      }
    }
  }
  var mytime = setTimeout(time, 10);
}
