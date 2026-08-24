<?php
/**
 * 아이템 상점 구매·판매 내역 (tb_item_trade_log)
 */

if (!function_exists('item_trade_log_스키마보장')) {
  function item_trade_log_스키마보장(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    @db_query("
      CREATE TABLE IF NOT EXISTS tb_item_trade_log (
        idx INT UNSIGNED NOT NULL AUTO_INCREMENT,
        side VARCHAR(8) NOT NULL DEFAULT 'buy',
        nick VARCHAR(32) NOT NULL DEFAULT '',
        midx INT UNSIGNED NOT NULL DEFAULT 0,
        item VARCHAR(64) NOT NULL DEFAULT '',
        qty INT UNSIGNED NOT NULL DEFAULT 1,
        unit_price DECIMAL(65,0) NOT NULL DEFAULT 0,
        paid_total DECIMAL(65,0) NOT NULL DEFAULT 0,
        surcharge DECIMAL(65,0) NOT NULL DEFAULT 0,
        fee DECIMAL(65,0) NOT NULL DEFAULT 0,
        net DECIMAL(65,0) NOT NULL DEFAULT 0,
        currency VARCHAR(16) NOT NULL DEFAULT 'point',
        channel VARCHAR(16) NOT NULL DEFAULT 'chat',
        room VARCHAR(16) NOT NULL DEFAULT '',
        regdate DATETIME NOT NULL,
        PRIMARY KEY (idx),
        KEY ix_nick_reg (nick, regdate),
        KEY ix_side_reg (side, regdate),
        KEY ix_item_reg (item, regdate)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
  }
}

if (!function_exists('item_trade_log_금액문자')) {
  function item_trade_log_금액문자($n): string {
    if (function_exists('냥_정수문자열')) {
      return 냥_정수문자열($n);
    }
    return ltrim(preg_replace('/\D/', '', (string)$n), '0') ?: '0';
  }
}

if (!function_exists('item_trade_log_기록')) {
  /**
   * @param array{
   *   side:string, nick:string, midx?:int, item:string, qty?:int,
   *   unit_price?:string|int, paid_total?:string|int, surcharge?:string|int,
   *   fee?:string|int, net?:string|int, currency?:string, channel?:string, room?:string
   * } $row
   */
  function item_trade_log_기록(array $row): bool {
    item_trade_log_스키마보장();

    $side = trim((string)($row['side'] ?? ''));
    if ($side !== 'buy' && $side !== 'sell') {
      return false;
    }
    $nick = trim((string)($row['nick'] ?? ''));
    $item = trim((string)($row['item'] ?? ''));
    if ($nick === '' || $item === '') {
      return false;
    }

    $midx = max(0, (int)($row['midx'] ?? 0));
    $qty = max(1, (int)($row['qty'] ?? 1));
    $unit = item_trade_log_금액문자($row['unit_price'] ?? '0');
    $paid = item_trade_log_금액문자($row['paid_total'] ?? '0');
    $surcharge = item_trade_log_금액문자($row['surcharge'] ?? '0');
    $fee = item_trade_log_금액문자($row['fee'] ?? '0');
    $net = item_trade_log_금액문자($row['net'] ?? '0');
    $currency = preg_replace('/[^a-z_]/', '', strtolower((string)($row['currency'] ?? 'point'))) ?: 'point';
    $channel = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($row['channel'] ?? 'chat'))) ?: 'chat';
    $room = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($row['room'] ?? ''))) ?: '';

    $nick_esc = addslashes($nick);
    $item_esc = addslashes(mb_substr($item, 0, 64, 'UTF-8'));
    $unit_sql = preg_replace('/\D/', '', $unit) ?: '0';
    $paid_sql = preg_replace('/\D/', '', $paid) ?: '0';
    $sur_sql = preg_replace('/\D/', '', $surcharge) ?: '0';
    $fee_sql = preg_replace('/\D/', '', $fee) ?: '0';
    $net_sql = preg_replace('/\D/', '', $net) ?: '0';
    $cur_esc = addslashes($currency);
    $ch_esc = addslashes($channel);
    $room_esc = addslashes($room);

    $ok = @db_query("
      INSERT INTO tb_item_trade_log
        (side, nick, midx, item, qty, unit_price, paid_total, surcharge, fee, net, currency, channel, room, regdate)
      VALUES
        ('{$side}', '{$nick_esc}', {$midx}, '{$item_esc}', {$qty},
         '{$unit_sql}', '{$paid_sql}', '{$sur_sql}', '{$fee_sql}', '{$net_sql}',
         '{$cur_esc}', '{$ch_esc}', '{$room_esc}', NOW())
    ");
    return (bool)$ok;
  }
}

if (!function_exists('item_trade_log_구매')) {
  function item_trade_log_구매(
    string $nick,
    string $item,
    int $qty,
    $unitPrice,
    $paidTotal,
    $surcharge = 0,
    int $midx = 0,
    string $channel = 'chat',
    string $room = '',
    string $currency = 'point'
  ): bool {
    return item_trade_log_기록([
      'side' => 'buy',
      'nick' => $nick,
      'midx' => $midx,
      'item' => $item,
      'qty' => $qty,
      'unit_price' => $unitPrice,
      'paid_total' => $paidTotal,
      'surcharge' => $surcharge,
      'fee' => 0,
      'net' => 0,
      'currency' => $currency,
      'channel' => $channel,
      'room' => $room,
    ]);
  }
}

if (!function_exists('item_trade_log_판매')) {
  function item_trade_log_판매(
    string $nick,
    string $item,
    int $qty,
    $grossTotal,
    $fee,
    $net,
    int $midx = 0,
    string $channel = 'chat',
    string $room = '',
    string $currency = 'point'
  ): bool {
    $qty = max(1, $qty);
    $gross = item_trade_log_금액문자($grossTotal);
    $unit = '0';
    if (function_exists('bcdiv') && function_exists('bccomp') && bccomp($gross, '0', 0) > 0) {
      $unit = bcdiv($gross, (string)$qty, 0);
    } elseif (strlen($gross) > 0 && $qty > 0) {
      // 대략 단가 (정수 나눗셈 문자열)
      if (function_exists('shop_문자열_나누기_내림')) {
        $unit = shop_문자열_나누기_내림($gross, $qty);
      } else {
        $unit = (string)(int)floor(((float)$gross) / $qty); // 소액 폴백
        if (strlen($gross) >= 16) {
          $unit = '0';
        }
      }
    }
    return item_trade_log_기록([
      'side' => 'sell',
      'nick' => $nick,
      'midx' => $midx,
      'item' => $item,
      'qty' => $qty,
      'unit_price' => $unit,
      'paid_total' => $gross,
      'surcharge' => 0,
      'fee' => $fee,
      'net' => $net,
      'currency' => $currency,
      'channel' => $channel,
      'room' => $room,
    ]);
  }
}
