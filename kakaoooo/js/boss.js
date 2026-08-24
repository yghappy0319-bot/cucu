(function () {
  var CODE = (window.BOSS_BOOT && window.BOSS_BOOT.code) || "";
  var MY_NICK = (window.BOSS_BOOT && window.BOSS_BOOT.nick) || "";
  var state = null;
  var bootReady = false;
  var busy = false;
  var tickLeft = 0;
  var tickMode = ''; // fight | wait
  var resultOpen = false;
  var counterTab = 'all'; // all | mine | hits
  var lastCounters = [];
  var lastMyHits = [];
  var lastCounterPrev = false;
  var pendingNextState = null;
  var lastFightSnap = null;
  var suppressFailUntil = 0;
  var lastSeenHitIdx = -1;
  var remoteFxBusy = false;
  var remoteFxQueue = [];

  function $(id) { return document.getElementById(id); }

  function fmtSec(sec) {
    sec = Math.max(0, Math.floor(sec || 0));
    var h = Math.floor(sec / 3600);
    var m = Math.floor((sec % 3600) / 60);
    var s = sec % 60;
    if (h > 0) return h + '시간 ' + m + '분 ' + s + '초';
    return m + '분 ' + s + '초';
  }

  function paintTimer() {
    var el = $('timer');
    if (!el) return;
    if (tickMode === 'fight') {
      el.className = 'timer' + (tickLeft <= 60 ? ' danger' : '');
      el.textContent = tickLeft > 0 ? ('⏱ 남은 시간 ' + fmtSec(tickLeft)) : '⏱ 타임 종료';
    } else if (tickMode === 'admin') {
      el.className = 'timer wait';
      el.textContent = '건의방 `.보스출현` 대기';
    } else if (tickMode === 'wait') {
      el.className = 'timer wait';
      el.textContent = tickLeft > 0 ? ('다음 출현까지 ' + fmtSec(tickLeft)) : '곧 출현…';
    } else {
      el.className = 'timer';
      el.textContent = '—';
    }
  }

  function counterTypeLabel(t) {
    if (t === 'item') return '아이템';
    if (t === 'point') return '게임냥';
    if (t === 'durability') return '내구도';
    if (t === 'protect') return '보호';
    if (t === 'swap') return '본방스왑';
    if (t === 'dodge') return '회피';
    if (t === 'aoe' || t === 'aoe_protect') return '광역';
    return t || '광역';
  }

  function shortTime(t) {
    if (!t) return '';
    var m = String(t).match(/(\d{2}:\d{2}:\d{2})/);
    return m ? m[1] : t;
  }

  function clearFx() {
    var layer = $('fxLayer');
    if (layer) layer.innerHTML = '';
  }

  function addDmgLabel(layer, damage, crit, color) {
    var dmg = document.createElement('div');
    dmg.className = 'fx-dmg';
    dmg.textContent = (crit ? '💥 ' : '') + '-' + Number(damage || 0).toLocaleString();
    if (crit) dmg.style.color = '#ff6b6b';
    else if (color) dmg.style.color = color;
    layer.appendChild(dmg);
  }

  function addProtectLabel(layer, amount, crit) {
    var dmg = document.createElement('div');
    dmg.className = 'fx-dmg';
    dmg.textContent = (crit ? '💥 ' : '🛡️ ') + '+' + Number(amount || 0).toLocaleString();
    dmg.style.color = crit ? '#ff6b6b' : '#9adbff';
    layer.appendChild(dmg);
  }

  function addAttackerLabel(layer, nick) {
    if (!nick) return;
    var el = document.createElement('div');
    el.className = 'fx-attacker';
    el.textContent = nick;
    layer.appendChild(el);
  }

  function playSwordFx(layer) {
    var slash = document.createElement('div');
    slash.className = 'fx-slash';
    layer.appendChild(slash);
    var slash2 = document.createElement('div');
    slash2.className = 'fx-slash second';
    layer.appendChild(slash2);
    var boom = document.createElement('div');
    boom.className = 'fx-boom';
    layer.appendChild(boom);
  }

  function playBowFx(layer) {
    var trail = document.createElement('div');
    trail.className = 'fx-arrow-trail';
    layer.appendChild(trail);
    var arrow = document.createElement('div');
    arrow.className = 'fx-arrow';
    layer.appendChild(arrow);
    var impact = document.createElement('div');
    impact.className = 'fx-arrow-impact';
    layer.appendChild(impact);
    var boom = document.createElement('div');
    boom.className = 'fx-boom';
    boom.style.animationDelay = '0.28s';
    boom.style.background = 'radial-gradient(circle, #fff 0%, #ffb45a 40%, transparent 70%)';
    layer.appendChild(boom);
  }

  function playMagicFx(layer) {
    var frost = document.createElement('div');
    frost.className = 'fx-frost';
    layer.appendChild(frost);
    var burst = document.createElement('div');
    burst.className = 'fx-ice-burst';
    layer.appendChild(burst);
    var angles = [-50, -20, 15, 45, 80, -80];
    for (var i = 0; i < angles.length; i++) {
      var shard = document.createElement('div');
      shard.className = 'fx-ice-shard';
      shard.style.setProperty('--rot', angles[i] + 'deg');
      shard.style.animationDelay = (i * 0.03) + 's';
      layer.appendChild(shard);
    }
  }

  function playMiningFx(layer) {
    var specs = [
      { left: '28%', size: 'lg', delay: '0s', fall: '112px', spin: '160deg' },
      { left: '46%', size: 'md', delay: '0.06s', fall: '120px', spin: '-120deg' },
      { left: '58%', size: 'sm', delay: '0.12s', fall: '108px', spin: '200deg' },
      { left: '38%', size: 'md', delay: '0.16s', fall: '126px', spin: '-90deg' },
      { left: '52%', size: 'lg', delay: '0.22s', fall: '116px', spin: '130deg' },
      { left: '64%', size: 'sm', delay: '0.28s', fall: '104px', spin: '-170deg' },
      { left: '34%', size: 'sm', delay: '0.34s', fall: '122px', spin: '80deg' }
    ];
    for (var i = 0; i < specs.length; i++) {
      var s = specs[i];
      var rock = document.createElement('div');
      rock.className = 'fx-rock ' + s.size;
      rock.style.left = s.left;
      rock.style.animationDelay = s.delay;
      rock.style.setProperty('--fall', s.fall);
      rock.style.setProperty('--spin', s.spin);
      layer.appendChild(rock);
    }
    var dustPositions = ['36%', '50%', '62%'];
    for (var d = 0; d < dustPositions.length; d++) {
      var dust = document.createElement('div');
      dust.className = 'fx-dust';
      dust.style.left = dustPositions[d];
      dust.style.animationDelay = (0.38 + d * 0.05) + 's';
      layer.appendChild(dust);
    }
    var boom = document.createElement('div');
    boom.className = 'fx-boom';
    boom.style.top = '60%';
    boom.style.animationDelay = '0.4s';
    boom.style.background = 'radial-gradient(circle, #fff 0%, #c9a06a 40%, transparent 70%)';
    layer.appendChild(boom);
  }

  function playHitFx(damage, crit, fx, attackerNick) {
    var emoji = $('bossEmoji');
    var card = $('bossCard');
    var layer = $('fxLayer');
    if (!emoji || !layer) return;
    clearFx();
    fx = fx || 'sword';
    emoji.classList.remove('waiting', 'hit', 'hit-bow', 'hit-magic', 'hit-mining', 'counter');
    void emoji.offsetWidth;
    if (card) {
      card.classList.remove('flash-hit', 'flash-bow', 'flash-magic', 'flash-mining', 'flash-counter');
      void card.offsetWidth;
    }
    if (attackerNick) addAttackerLabel(layer, attackerNick);
    if (fx === 'bow') {
      emoji.classList.add('hit-bow');
      if (card) card.classList.add('flash-bow');
      playBowFx(layer);
      addDmgLabel(layer, damage, crit, '#ffd28a');
    } else if (fx === 'magic') {
      emoji.classList.add('hit-magic');
      if (card) card.classList.add('flash-magic');
      playMagicFx(layer);
      addDmgLabel(layer, damage, crit, '#9adbff');
    } else if (fx === 'mining') {
      emoji.classList.add('hit-mining');
      if (card) card.classList.add('flash-mining');
      playMiningFx(layer);
      addDmgLabel(layer, damage, crit, '#e8c48a');
    } else if (fx === 'protect') {
      emoji.classList.add('hit-magic');
      if (card) card.classList.add('flash-magic');
      playMagicFx(layer);
      addProtectLabel(layer, damage, crit);
    } else if (fx === 'finish') {
      emoji.classList.add('hit');
      if (card) card.classList.add('flash-hit');
      playSwordFx(layer);
      addDmgLabel(layer, damage, crit, '#ff8a6b');
    } else {
      emoji.classList.add('hit');
      if (card) card.classList.add('flash-hit');
      playSwordFx(layer);
      addDmgLabel(layer, damage, crit, null);
    }
    setTimeout(function () {
      emoji.classList.remove('hit', 'hit-bow', 'hit-magic', 'hit-mining');
      if (card) card.classList.remove('flash-hit', 'flash-bow', 'flash-magic', 'flash-mining');
      clearFx();
    }, 900);
  }

  function bootstrapHitCursor(s) {
    var hits = (s && s.recent_hits) || [];
    var max = 0;
    for (var i = 0; i < hits.length; i++) {
      max = Math.max(max, Number(hits[i].idx || 0));
    }
    lastSeenHitIdx = max;
  }

  function drainRemoteFxQueue() {
    if (remoteFxBusy || resultOpen) return;
    if (busy) return; // 내 공격 처리 중이면 잠깐 대기
    if (!remoteFxQueue.length) return;
    remoteFxBusy = true;
    var h = remoteFxQueue.shift();
    var fx = h.weapon_fx || (h.attack_type === 'mining' ? 'mining' : (h.attack_type === 'protect' ? 'protect' : (h.attack_type === 'finish' ? 'finish' : 'sword')));
    playHitFx(h.damage || 0, !!h.crit, fx, h.nick || '');
    setTimeout(function () {
      remoteFxBusy = false;
      drainRemoteFxQueue();
    }, 380);
  }

  function applyRemoteHits(s) {
    if (!s || s.phase !== 'fighting') return;
    var hits = s.recent_hits || [];
    if (!hits.length) return;
    for (var i = 0; i < hits.length; i++) {
      var h = hits[i];
      var idx = Number(h.idx || 0);
      if (idx <= lastSeenHitIdx) continue;
      lastSeenHitIdx = Math.max(lastSeenHitIdx, idx);
      if ((h.nick || '') === MY_NICK) continue;
      if ((h.attack_type || '') === 'protect' || (h.weapon_fx || '') === 'protect') continue;
      remoteFxQueue.push(h);
    }
    drainRemoteFxQueue();
  }

  function playCounterFx(counter) {
    pushCounterStatus(counter || null);
    if (!counter || !counter.type) return;
    var emoji = $('bossEmoji');
    var card = $('bossCard');
    var layer = $('fxLayer');
    if (!emoji || !layer) return;
    emoji.classList.remove('waiting', 'hit', 'hit-bow', 'hit-magic', 'hit-mining', 'counter');
    void emoji.offsetWidth;
    emoji.classList.add('counter');
    if (card) {
      card.classList.remove('flash-hit', 'flash-bow', 'flash-magic', 'flash-mining', 'flash-counter');
      void card.offsetWidth;
      card.classList.add('flash-counter');
    }
    var label = document.createElement('div');
    label.className = 'fx-counter-label';
    var t = counter.type || '';
    var detail = counter.detail || '보스 광역!';
    label.textContent = '🐉 ' + (detail || counterTypeLabel(t));
    layer.appendChild(label);
    setTimeout(function () {
      emoji.classList.remove('counter');
      if (card) card.classList.remove('flash-counter');
    }, 650);
  }

  function personalCounterFromAoe(counter) {
    if (!counter || !counter.hits || !counter.hits.length || !MY_NICK) return null;
    for (var i = 0; i < counter.hits.length; i++) {
      if ((counter.hits[i].nick || '') === MY_NICK) {
        return counter.hits[i];
      }
    }
    return null;
  }

  function pushCounterStatus(counter) {
    var box = $('counterStatus');
    if (!box) return;
    if (!counter || !counter.type) {
      return;
    }
    var mine = personalCounterFromAoe(counter);
    var view = mine || counter;
    var t = view.type || counter.type || '';
    box.classList.remove('idle');
    $('csIco').textContent = counterIcon(t);
    if (t === 'dodge') {
      $('csType').textContent = '회피';
      $('csSub').textContent = '피해 없음';
    } else if (t === 'aoe' || t === 'aoe_protect') {
      var wave = counter.wave ? (counter.wave + '/' + (counter.max || 5)) : '';
      $('csType').textContent = '광역 공격' + (wave ? ' (' + wave + ')' : '');
      $('csSub').textContent = mine
        ? '내 피해 · 아래 이력에서 전체 확인'
        : '참여자 전원 광역';
    } else {
      $('csType').textContent = counterTypeLabel(t) + ' 피해';
      $('csSub').textContent = mine ? '내게 적용된 광역 결과' : '광역/반격 결과';
    }
    $('csLoss').textContent = counterLossText(view);
  }

  /** 새로고침/폴링 시에도 광역 진행·내 최근 피해 표시 */
  function syncCounterStatusFromState(s) {
    var box = $('counterStatus');
    if (!box || !s) return;
    if (s.phase === 'waiting') {
      box.classList.add('idle');
      $('csIco').textContent = '🛡️';
      $('csType').textContent = '대기 중';
      $('csLoss').textContent = '전투 중 광역이 여기 표시됩니다';
      $('csSub').textContent = s.has_prev_result
        ? '이전 보스 피해는 「이전 보스 내역」에서 확인'
        : '광역은 제한시간 동안 최대 5회 · 본인 누적딜 3% 보호 차감';
      return;
    }

    var waveNow = Number(s.aoe_protect_count || 0);
    var waveMax = Number(s.aoe_protect_max || 5);
    var mineRows = (s.counters || []).filter(function (row) {
      return (row.nick || '') === MY_NICK;
    });
    if (mineRows.length) {
      var last = mineRows[0];
      box.classList.remove('idle');
      $('csIco').textContent = counterIcon(last.type);
      $('csType').textContent = '광역 ' + waveNow + '/' + waveMax + ' · 내 최근 피해';
      $('csLoss').textContent = counterLossText(last);
      $('csSub').textContent = counterTypeLabel(last.type) +
        (last.time ? (' · ' + shortTime(last.time)) : '') +
        ' · 아래 「내 피해」탭에서 전체 확인';
      return;
    }

    box.classList.toggle('idle', waveNow < 1);
    $('csIco').textContent = waveNow > 0 ? '🌋' : '🛡️';
    $('csType').textContent = waveNow > 0
      ? ('광역 ' + waveNow + '/' + waveMax + ' 발동됨')
      : ('광역 대기 · 0/' + waveMax);
    $('csLoss').textContent = waveNow > 0
      ? '참여자 전원 광역이 발동했어요 (내 피해 기록 없음)'
      : '아직 광역 없음 · 전투 경과에 따라 최대 ' + waveMax + '회';
    $('csSub').textContent = '참여자별 본인 누적딜의 3% 보호 차감 · 보호 0이면 성향 피해';
  }

  function counterIcon(t) {
    if (t === 'item') return '🎒';
    if (t === 'point') return '💰';
    if (t === 'durability') return '⚔️';
    if (t === 'protect') return '🛡️';
    if (t === 'aoe' || t === 'aoe_protect') return '🌋';
    if (t === 'swap') return '💱';
    if (t === 'dodge') return '✨';
    return '🐉';
  }

  function counterLossText(c) {
    var t = (c && c.type) || '';
    var detail = (c && c.detail) || '';
    var amount = (c && c.amount) != null ? String(c.amount) : '';
    if (detail) return detail;
    if (t === 'dodge') return '회피! (피해 없음)';
    if (t === 'item') {
      var names = (c && c.items && c.items.length) ? c.items.join(', ') : '';
      return names
        ? ('가방 아이템 ' + (amount || c.items.length) + '개 소멸 (' + names + ')')
        : ('가방 아이템 ' + (amount || '?') + '개 소멸');
    }
    if (t === 'point') return '게임냥 → 처치풀';
    if (t === 'durability') return '무기 내구도 하락';
    if (t === 'protect') return '보호 -' + (amount || '?');
    if (t === 'aoe' || t === 'aoe_protect') return '광역 보호 차감 · 본인 누적딜 3%';
    if (t === 'swap') return '본방냥 10% → 게임냥 강제스왑';
    return '보스 광역 피해를 받았어요.';
  }

  function rememberFightSnap(s) {
    if (s && s.phase === 'fighting') {
      lastFightSnap = {
        emoji: s.emoji || '🐉',
        name: s.name || '보스',
        boss_idx: Number(s.boss_idx || 0),
        hp_now: Number(s.hp_now),
        hp_pct: Number(s.hp_pct)
      };
    }
  }

  /** 서버 last_end_status(1=처치 2=타임오버) 우선 · 폴링 레이스로 오판정 방지 */
  function resolveFightEnd(next, snap) {
    snap = snap || {};
    var endStatus = Number(next && next.last_end_status);
    if (endStatus === 1) {
      return {
        kill: true,
        emoji: (next && next.last_end_emoji) || snap.emoji || '🏆',
        name: (next && next.last_end_name) || snap.name || '보스'
      };
    }
    if (endStatus === 2) {
      return {
        kill: false,
        emoji: (next && next.last_end_emoji) || snap.emoji || '🐉',
        name: (next && next.last_end_name) || snap.name || '보스'
      };
    }
    var hpNow = Number(snap.hp_now);
    var hpPct = Number(snap.hp_pct);
    var hpKill = (Number.isFinite(hpNow) && hpNow === 0)
      || (Number.isFinite(hpPct) && hpPct === 0);
    var kill = hpKill || (Date.now() < suppressFailUntil);
    return {
      kill: kill,
      emoji: snap.emoji || (kill ? '🏆' : '🐉'),
      name: snap.name || '보스'
    };
  }

  function showResultLayer(kind, opts) {
    opts = opts || {};
    var ov = $('resultOverlay');
    var panel = $('resultPanel');
    if (!ov || !panel) return;
    resultOpen = true;
    var win = kind === 'kill';
    panel.className = 'result-panel ' + (win ? 'win' : 'fail');
    $('resultIco').textContent = opts.emoji || (win ? '🏆' : '⏱');
    $('resultTitle').textContent = win ? '보스 처치!' : '처치 실패!';
    $('resultBadge').textContent = opts.badge || (win ? '성공' : '타임오버');
    $('resultBody').textContent = opts.body || (win ? '보스를 처치했어요!' : '제한 시간 안에 보스를 잡지 못했어요.');
    $('resultSub').textContent = opts.sub || '확인하면 다음 보스 대기로 넘어갑니다.';
    $('resultClose').textContent = '다음으로';
    ov.classList.add('show');
  }

  function hideResultLayer() {
    var ov = $('resultOverlay');
    if (ov) ov.classList.remove('show');
    resultOpen = false;
    var next = pendingNextState;
    pendingNextState = null;
    if (next) {
      render(next);
    } else {
      refreshState();
    }
  }

  var lastDropKey = '';
  function dropTickerText(d) {
    if (!d) return '';
    if (d.detail) return d.detail;
    if (d.type === 'eunchong') return '🌟 ' + (d.nick || '누군가') + ' 님이 은총 +' + (d.qty || 1) + ' 획득!';
    if (d.type === 'shard') return '✨ ' + (d.nick || '누군가') + ' 님이 은총조각 +' + (d.qty || 1) + ' 획득!';
    return '';
  }
  function showDropTicker(d) {
    var el = $('dropTicker');
    if (!el || !d) return;
    var text = dropTickerText(d);
    if (!text) return;
    var key = (d.time || '') + '|' + (d.nick || '') + '|' + (d.type || '') + '|' + text;
    el.textContent = text;
    if ((d.type || '') === 'eunchong') el.classList.add('eunchong');
    else el.classList.remove('eunchong');
    if (key !== lastDropKey) {
      lastDropKey = key;
      el.classList.remove('show');
      void el.offsetWidth;
    }
    el.classList.add('show');
  }
  function applyDropTicker(s, attackDrops) {
    if (attackDrops && attackDrops.length) {
      var first = attackDrops[0];
      showDropTicker({
        nick: '',
        type: first.type || '',
        qty: first.qty || 1,
        detail: first.detail || first.msg || '',
        time: String(Date.now())
      });
      return;
    }
    if (!s) return;
    if (s.drop_latest) {
      showDropTicker(s.drop_latest);
      return;
    }
    var drops = s.drops || [];
    if (drops.length) {
      showDropTicker(drops[0]);
    }
  }

  function applyHp(s) {
    if (!s) return;
    $('hpNow').textContent = (s.hp_now || 0).toLocaleString();
    $('hpMax').textContent = (s.hp_max || 0).toLocaleString();
    $('hpPct').textContent = (s.hp_pct || 0) + '%';
    $('hpFill').style.width = Math.max(0, Math.min(100, s.hp_pct || 0)) + '%';
    var loot = $('stolenLoot');
    if (loot) {
      var waiting = (s.phase === 'waiting');
      var fmt = s.stolen_point_fmt || '0';
      loot.style.display = '';
      loot.textContent = '보스 처치풀 ' + fmt;
    }
  }

  function render(s) {
    if (!s) return;
    if (resultOpen && s.phase === 'waiting' && pendingNextState) {
      // 결과 레이어 보이는 동안엔 대기 UI로 갈아타지 않음
      pendingNextState = s;
      return;
    }
    state = s;
    rememberFightSnap(s);
    var waiting = (s.phase === 'waiting');
    applyDropTicker(s, null);
    $('bossEmoji').textContent = s.emoji || '🐉';
    if (!$('bossEmoji').classList.contains('hit')
      && !$('bossEmoji').classList.contains('hit-bow')
      && !$('bossEmoji').classList.contains('hit-magic')
      && !$('bossEmoji').classList.contains('hit-mining')
      && !$('bossEmoji').classList.contains('counter')) {
      $('bossEmoji').className = 'dragon' + (waiting ? ' waiting' : '');
    }
    $('bossName').textContent = waiting
      ? (s.need_admin ? '출현 대기' : ('다음 · ' + (s.name || '보스')))
      : (s.name || '보스');
    (function updateDocTitle() {
      var nm = (s.name || '보스').trim();
      var em = (s.emoji || '').trim();
      if (waiting) {
        document.title = s.need_admin ? '보스 · 출현 대기' : ('보스 · 다음 ' + nm);
      } else {
        document.title = '보스 · ' + (em ? (em + ' ') : '') + nm;
      }
    })();
    var styleBit = s.style_label ? (s.style_label + ' · ') : '';
    $('bossSub').textContent = waiting
      ? (s.need_admin
          ? '건의방 관리자 `.보스출현` 으로 시작 · 랜덤 보스 · 제한 30분'
          : (styleBit + (s.blurb || '3~5시간 후 로테이션')
              + ' · HP ' + Number(s.hp_max || 0).toLocaleString()
              + (s.night_hp_half ? ' (새벽 50%)' : '')))
      : (styleBit + (s.blurb || '광역 최대 5회') + ' · ' + (s.fight_min || 5) + '분 제한'
          + ' · 광역 ' + Number(s.aoe_protect_count || 0) + '/' + Number(s.aoe_protect_max || 5)
          + (s.night_hp_half ? ' · 🌙새벽HP·30분' : ''));
    applyHp(s);

    if (waiting) {
      tickMode = s.need_admin ? 'admin' : 'wait';
      tickLeft = Number(s.next_spawn_in || 0);
    } else {
      tickMode = 'fight';
      tickLeft = Number(s.fight_left_sec || 0);
    }
    paintTimer();

    var startCard = $('adminStartCard');
    if (startCard) {
      startCard.hidden = !waiting;
      var hint = $('adminStartHint');
      if (hint) {
        var who = ((s.emoji || '') + ' ' + (s.name || '보스')).trim();
        hint.textContent = waiting
          ? (s.need_admin
              ? (who + ' · 출현 대기 · 누르면 바로 시작')
              : (who + ' · 다음 출현까지 대기 중 · 누르면 대기 건너뛰고 시작'))
          : '대기 중인 보스가 없어요.';
      }
    }

    var shardHave = Number(s.eunchong_shard || 0);
    var isMagic = !!s.is_magic;
    var isDanso = !!s.is_danso;
    var showShardWeapon = !!s.show_shard_weapon;
    var showShardMining = !isMagic && !isDanso && !!s.show_shard_mining;
    var showShardFinish = isDanso && !!s.show_shard_finish;
    var canShardWeapon = !!s.can_shard_weapon;
    var canShardMining = !isMagic && !isDanso && !!s.can_shard_mining;
    var canShardFinish = isDanso && !!s.can_shard_finish;
    var btnWeapon = $('btnWeapon');
    var btnMining = $('btnMining');
    btnWeapon.disabled = showShardWeapon ? !canShardWeapon : !s.can_weapon;
    btnMining.disabled = isMagic
      ? !s.can_protect
      : (isDanso
        ? (showShardFinish ? !canShardFinish : !s.can_finish)
        : (showShardMining ? !canShardMining : !s.can_mining));
    btnWeapon.classList.toggle('shard-mode', showShardWeapon);
    btnMining.classList.toggle('shard-mode', showShardMining || showShardFinish);
    btnMining.classList.toggle('protect', isMagic);
    btnMining.classList.toggle('finish', isDanso && !showShardFinish);
    btnMining.classList.toggle('mining', !isMagic && !isDanso);
    btnWeapon.childNodes[0].nodeValue = showShardWeapon
      ? '✨ 무기 횟수·내구 초기화'
      : '⚔️ 무기 공격';
    btnMining.childNodes[0].nodeValue = isMagic
      ? '🛡️ 보호'
      : (isDanso
        ? (showShardFinish ? '✨ 필살 횟수 초기화' : '💥 필살')
        : (showShardMining
          ? '✨ 채굴 횟수·내구 초기화'
          : '🥄 채굴 공격'));
    var wLeft = Number(s.weapon_left || 0);
    var wMax = Number(s.weapon_max || 0);
    var execOn = !!s.danso_execute_on;
    $('weaponHint').textContent = showShardWeapon
      ? '은총조각 1개 · 보유 ' + shardHave + '개 · 내구 100%'
      : (isDanso
        ? ('남은 ' + wLeft + '/' + wMax + ' · +' + (s.enhance || 0) + (execOn ? ' · HP50%↓ ×2' : ''))
        : ('남은 ' + wLeft + '/' + wMax + ' · +' + (s.enhance || 0)));
    var mUsed = Number(s.mining_used || 0);
    var mMax = Number(s.mining_max != null ? s.mining_max : (s.mining_level || 0));
    var mLeft = Number(s.mining_left != null ? s.mining_left : Math.max(0, mMax - mUsed));
    var pUsed = Number(s.protect_used || 0);
    var pMax = Number(s.protect_max != null ? s.protect_max : wMax);
    var pLeft = Number(s.protect_left != null ? s.protect_left : Math.max(0, pMax - pUsed));
    var fUsed = Number(s.finish_used || 0);
    var fMax = Number(s.finish_max != null ? s.finish_max : 0);
    var fLeft = Number(s.finish_left != null ? s.finish_left : Math.max(0, fMax - fUsed));
    if (isMagic) {
      $('miningHint').textContent = pLeft > 0
        ? ('남은 ' + pLeft + '/' + pMax + ' · 전원 강화~2배')
        : ('이번 보스 한도 소진');
    } else if (isDanso) {
      var fReset = Number(s.finish_reset_in || 0);
      if (showShardFinish) {
        $('miningHint').textContent = '은총조각 1개 · 보유 ' + shardHave + '개 · 1시간 다시';
      } else {
        $('miningHint').textContent = fLeft > 0
          ? ('남은 ' + fLeft + '/' + fMax + ' · 무기×3~6')
          : (fReset > 0 ? (fmtSec(fReset) + ' 후 초기화') : '1시간 한도 소진');
      }
    } else {
      $('miningHint').textContent = showShardMining
        ? '은총조각 1개 · 보유 ' + shardHave + '개 · 내구 100%'
        : '남은 ' + mLeft + '/' + mMax + ' · ' + (s.mining_label || ('Lv' + (s.mining_level || 0)));
    }

    var joinNote = s.can_join ? '' : '<br><span style="color:#ff6b6b">무기 +' + (s.min_enhance || 15) + ' 이상부터 공격 가능 (현재 +' + (s.enhance || 0) + ')</span>';
    var durCur = Number(s.weapon_dur_current != null ? s.weapon_dur_current : (s.weapon_durability || 0));
    var durMax = Number(s.weapon_dur_max || 0);
    var durLabel = durMax > 0 ? (durCur + '/' + durMax) : String(durCur);
    var durNote = s.weapon_dur_blocked
      ? '<br><span style="color:#ff6b6b">무기 내구 10% 미만 · 공격 불가 (현재 ' + durLabel + ')</span>'
      : '';
    var miningLine = isMagic
      ? ('마법 보호 <b>' + pUsed + '/' + pMax + '</b> (이번 보스 · 채팅 .보호와 별개)')
      : (isDanso
        ? ('단소 필살 <b>' + fUsed + '/' + fMax + '</b> (1시간 · 채팅 .시전과 별개)')
        : ('채굴 <b>' + (s.mining_label || ('Lv' + (s.mining_level || 0))) + '</b>' +
          ' · 공격 ' + mUsed + '/' + mMax + ' (레벨만큼 · 일반 수리해도 횟수 회복 없음)'));
    $('myInfo').innerHTML =
      '무기 강화 <b>+' + (s.enhance || 0) + '</b>' + (s.weapon_item ? ' (' + s.weapon_item + ')' : '') +
      ' · 공격 ' + (s.weapon_used || 0) + '/' + wMax +
      ' · 내구 <b>' + durLabel + '</b><br>' +
      miningLine +
      ' · 은총조각 <b>' + shardHave + '</b>' +
      joinNote + durNote;

    $('partCnt').textContent = String(s.participants || 0);
    $('rewardFmt').textContent = s.reward_each_fmt || '0';
    var rp = $('rewardPct');
    if (rp) rp.textContent = '본방냥 ' + (s.reward_pct_label || '1%');

    var histCard = $('histCard');
    var rankCard = $('rankCard');
    var counterLogCard = $('counterLogCard');
    if (waiting) {
      if (histCard) histCard.hidden = !s.has_prev_result;
      if (rankCard) rankCard.hidden = true;
      if (counterLogCard) counterLogCard.hidden = true;
      lastCounters = [];
      lastMyHits = [];
      lastCounterPrev = false;
      syncCounterStatusFromState(s);
      return;
    }
    if (histCard) histCard.hidden = true;
    if (rankCard) rankCard.hidden = false;
    if (counterLogCard) counterLogCard.hidden = false;

    var rankTitle = $('rankTitle');
    if (rankTitle) rankTitle.textContent = '기여 순위';
    updateCounterTitle(false, '');
    var rank = s.rank || [];
    var html = '';
    if (!rank.length) {
      html = '<li><span>아직 공격 없음</span><span>—</span></li>';
    } else {
      for (var i = 0; i < rank.length; i++) {
        var r = rank[i];
        var dmg = Number(r.damage || 0).toLocaleString();
        var pct = (typeof r.share_pct !== 'undefined') ? Number(r.share_pct) : null;
        var pay = Number(r.reward || 0);
        var payLabel = r.reward_label || '';
        var sub = [];
        if (pct !== null && !isNaN(pct)) {
          sub.push(pct.toFixed(1) + '%');
        }
        if (payLabel && pay > 0) {
          sub.push('<span class="pay">' + payLabel + ' +' + Number(pay).toLocaleString() + '냥</span>');
        }
        html += '<li><span>' + (i + 1) + ') ' + (r.nick || '')
          + '<small class="who-protect">보호 ' + Number(r.protect || 0).toLocaleString() + '</small></span>'
          + '<span class="rank-right"><b>' + dmg + '</b>'
          + (sub.length ? ('<small>' + sub.join(' · ') + '</small>') : '')
          + '</span></li>';
      }
    }
    $('rankList').innerHTML = html;

    lastCounters = s.counters || [];
    lastMyHits = s.my_hits || [];
    lastCounterPrev = false;
    paintCounterList();
    syncCounterStatusFromState(s);
  }

  function updateCounterTitle(prevResult, prevLabel) {
    var counterTitle = $('counterTitle');
    if (!counterTitle) return;
    if (counterTab === 'hits') {
      counterTitle.textContent = prevResult
        ? ('이전 내 공격 · ' + (prevLabel || '직전 보스'))
        : '내 공격 기록';
      return;
    }
    if (counterTab === 'mine') {
      counterTitle.textContent = prevResult
        ? ('이전 내 피해 · ' + (prevLabel || '직전 보스'))
        : '내 광역 피해';
      return;
    }
    counterTitle.textContent = prevResult
      ? ('이전 광역 이력 · ' + (prevLabel || '직전 보스'))
      : '보스 광역 이력';
  }

  function attackTypeLabel(t) {
    if (t === 'mining') return '채굴';
    if (t === 'protect') return '보호';
    if (t === 'finish') return '필살';
    return '무기';
  }

  function paintCounterList() {
    var list = $('counterList');
    if (!list) return;
    updateCounterTitle(lastCounterPrev, (state && state.prev_result_label) || '');

    if (counterTab === 'hits') {
      var hits = lastMyHits || [];
      var hlog = '';
      if (!hits.length) {
        hlog = lastCounterPrev
          ? '<li><span>이전 보스 공격 기록 없음</span><span>—</span></li>'
          : '<li><span>아직 내 공격 없음</span><span>—</span></li>';
      } else {
        var totalDmg = 0;
        for (var h = 0; h < hits.length; h++) {
          var hit = hits[h];
          var dmg = Number(hit.damage || 0);
          var kind = attackTypeLabel(hit.attack_type);
          var critMark = hit.crit ? ' · 💥치명' : '';
          var isProtect = hit.attack_type === 'protect';
          var isFinish = hit.attack_type === 'finish';
          var power = isProtect
            ? ('전원 +' + (hit.mining_level || 0))
            : (isFinish
              ? ('필살 ×' + (hit.mining_level || hit.mult || 0))
              : ((hit.attack_type === 'mining')
                ? ('채굴 Lv' + (hit.mining_level || 0))
                : ('무기 +' + (hit.enhance || 0))));
          var right = isProtect
            ? ('<span class="dmg">+' + Number(hit.mining_level || 0).toLocaleString() + '</span>')
            : ('<span class="dmg">-' + dmg.toLocaleString() + '</span>');
          if (!isProtect) totalDmg += dmg;
          hlog += '<li><span><span class="ok">' + kind + '</span> · ' + power + critMark +
            '</span><span class="hit-meta">' + right +
            '<span class="time">' + shortTime(hit.time) + '</span></span></li>';
        }
        hlog = '<li><span><span class="who">합계 ' + hits.length + '회</span></span>' +
          '<span class="dmg">-' + totalDmg.toLocaleString() + '</span></li>' + hlog;
      }
      list.innerHTML = hlog;
      return;
    }

    var counters = lastCounters || [];
    var mineOnly = counterTab === 'mine';
    var rows = counters;
    if (mineOnly) {
      rows = counters.filter(function (row) {
        return (row.nick || '') === MY_NICK;
      });
    }
    var clog = '';
    if (!rows.length) {
      if (mineOnly) {
        clog = lastCounterPrev
          ? '<li><span>이전 보스에서 받은 피해 없음</span><span>—</span></li>'
          : '<li><span>아직 내게 적용된 피해 없음</span><span>—</span></li>';
      } else {
        clog = lastCounterPrev
          ? '<li><span>이전 보스 광역 기록 없음</span><span>—</span></li>'
          : '<li><span>아직 광역 없음</span><span>—</span></li>';
      }
    } else {
      for (var c = 0; c < rows.length; c++) {
        var row = rows[c];
        if (mineOnly) {
          clog += '<li><span><span class="bad">' +
            counterTypeLabel(row.type) + '</span> · ' + (row.detail || '') +
            '</span><span class="time">' + shortTime(row.time) + '</span></li>';
        } else {
          clog += '<li><span><span class="who">' + (row.nick || '') + '</span> ← <span class="bad">' +
            counterTypeLabel(row.type) + '</span> · ' + (row.detail || '') +
            '</span><span class="time">' + shortTime(row.time) + '</span></li>';
        }
      }
    }
    list.innerHTML = clog;
  }

  function setMsg(text, kind) {
    var el = $('msg');
    el.className = 'msg' + (kind ? (' ' + kind) : '');
    el.textContent = text || '';
  }

  function post(action, extra) {
    var body = new URLSearchParams();
    body.set('code', CODE);
    body.set('action', action);
    if (extra) {
      Object.keys(extra).forEach(function (k) { body.set(k, extra[k]); });
    }
    return fetch(location.pathname + location.search, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function (r) { return r.json(); });
  }

  function refreshState(opts) {
    if (resultOpen) {
      return Promise.resolve();
    }
    var lite = !!(opts && opts.lite);
    var prevPhase = state && state.phase;
    return post('state', lite ? { lite: '1' } : null).then(function (j) {
      if (!(j && j.ok && j.state)) return;
      var next = j.state;
      // lite 폴링은 이력·로스터를 비울 수 있음 → 화면 깜빡임 방지
      if (lite && state) {
        if ((!next.counters || !next.counters.length) && state.counters) next.counters = state.counters;
        if ((!next.my_hits || !next.my_hits.length) && state.my_hits) next.my_hits = state.my_hits;
        if ((!next.drops || !next.drops.length) && state.drops) next.drops = state.drops;
        if ((!next.roster || !next.roster.length) && state.roster) next.roster = state.roster;
        if (!next.prev_result && state.prev_result) {
          next.prev_result = state.prev_result;
          next.prev_result_label = state.prev_result_label;
        }
      }
      if (prevPhase === 'fighting' && next.phase === 'waiting') {
        pendingNextState = next;
        var snap = lastFightSnap || {};
        var end = resolveFightEnd(next, snap);
        var isKill = !!end.kill;
        var resultOpts = {
          emoji: end.emoji || (isKill ? '🏆' : '🐉'),
          badge: isKill ? '성공' : '타임오버',
          body: isKill
            ? ((end.name || '보스') + ' 처치!')
            : ((end.name ? (end.name + ' 처치 실패') : '제한 시간 안에 보스를 잡지 못했어요.')),
          sub: '확인하면 다음 보스 대기로 넘어갑니다.'
        };
        if (resultOpen) {
          // 폴링이 공격응답보다 빨라 실패로 먼저 뜬 경우 → 처치 확정이면 교체
          if (isKill) {
            showResultLayer('kill', resultOpts);
          }
          return;
        }
        if (Date.now() < suppressFailUntil && isKill) {
          return;
        }
        showResultLayer(isKill ? 'kill' : 'fail', resultOpts);
        if (state && state.phase === 'fighting') {
          state.hp_now = 0;
          state.hp_pct = 0;
          state.fight_left_sec = 0;
          applyHp(state);
          tickLeft = 0;
          paintTimer();
        }
        return;
      }
      render(next);
      applyRemoteHits(next);
    }).catch(function () {});
  }

  function attack(type) {
    if (!bootReady || busy || resultOpen) return;
    busy = true;
    setMsg(type === 'weapon' ? '무기 공격 중…' : (type === 'finish' ? '필살 시전 중…' : '채굴 공격 중…'), '');
    post('attack', { type: type }).then(function (j) {
      if (j && j.ok) {
        // HP는 FX보다 먼저 반영해서 딜레이감 줄임
        if (j.state && !j.killed) applyHp(j.state);
        playHitFx(j.damage || 0, !!j.crit, j.weapon_fx || (type === 'mining' ? 'mining' : (type === 'finish' ? 'finish' : (state && state.weapon_fx) || 'sword')));
        // 내 타격은 이미 재생했으므로 커서만 전진 (중복 재생 방지)
        if (j.state && j.state.recent_hits) {
          applyRemoteHits(j.state);
        }
        if (j.drops && j.drops.length) {
          applyDropTicker(j.state || null, j.drops);
        }
        if (j.killed) {
          suppressFailUntil = Date.now() + 8000;
          if (j.state) {
            applyHp({ hp_now: 0, hp_pct: 0, stolen_point_fmt: (state && state.stolen_point_fmt) || '0' });
          }
          pendingNextState = j.state || null;
          var snap = lastFightSnap || state || {};
          var killBody = (j.kill && j.kill.msg) ? j.kill.msg : (j.data || '보스를 처치했어요!');
          setTimeout(function () {
            showResultLayer('kill', {
              emoji: snap.emoji || '🏆',
              badge: (snap.name || '보스') + ' 처치',
              body: killBody,
              sub: '확인하면 다음 보스 대기로 넘어갑니다.'
            });
          }, 420);
          setMsg(j.data || '보스 처치!', 'ok');
          return;
        }
        if (j.counter && j.counter.type) {
          setTimeout(function () {
            playCounterFx(j.counter);
          }, 280);
        } else if (j.aoe && j.aoe.type) {
          setTimeout(function () {
            playCounterFx(j.aoe);
          }, 280);
        }
        render(j.state || j);
        if (j.drops && j.drops.length) {
          applyDropTicker(j.state || null, j.drops);
        }
        setMsg(j.data || '공격 성공!', 'ok');
      } else {
        setMsg((j && j.data) ? j.data : '공격 실패', 'err');
        if (j && j.state) {
          var prevPhase = state && state.phase;
          if (prevPhase === 'fighting' && j.state.phase === 'waiting') {
            pendingNextState = j.state;
            var snap2 = lastFightSnap || state || {};
            var end2 = resolveFightEnd(j.state, snap2);
            var isKill2 = !!end2.kill;
            if (isKill2) {
              suppressFailUntil = Date.now() + 8000;
            }
            showResultLayer(isKill2 ? 'kill' : 'fail', {
              emoji: end2.emoji || (isKill2 ? '🏆' : '🐉'),
              badge: isKill2 ? '성공' : '타임오버',
              body: isKill2
                ? ((end2.name || '보스') + ' 처치!')
                : (j.data || '제한 시간 안에 보스를 잡지 못했어요.'),
              sub: '확인하면 다음 보스 대기로 넘어갑니다.'
            });
            return;
          }
          render(j.state);
        }
      }
    }).catch(function () {
      setMsg('네트워크 오류', 'err');
    }).finally(function () {
      busy = false;
      drainRemoteFxQueue();
    });
  }

  $('btnWeapon').addEventListener('click', function () {
    if (state && state.show_shard_weapon) shardCharge('weapon');
    else attack('weapon');
  });
  $('btnMining').addEventListener('click', function () {
    if (state && state.is_magic) protectParty();
    else if (state && state.is_danso) {
      if (state.show_shard_finish) shardCharge('finish');
      else attack('finish');
    }
    else if (state && state.show_shard_mining) shardCharge('mining');
    else attack('mining');
  });

  function protectParty() {
    if (!bootReady || busy || resultOpen) return;
    busy = true;
    setMsg('파티 보호 중…', '');
    post('protect').then(function (j) {
      if (j && j.ok) {
        playHitFx(j.amount || 0, !!j.crit, 'protect');
        if (j.state && j.state.recent_hits) {
          applyRemoteHits(j.state);
        }
        render(j.state || j);
        setMsg(j.data || '파티 보호!', 'ok');
      } else {
        setMsg((j && j.data) ? j.data : '보호 실패', 'err');
        if (j && j.state) render(j.state);
      }
    }).catch(function () {
      setMsg('네트워크 오류', 'err');
    }).finally(function () {
      busy = false;
      drainRemoteFxQueue();
    });
  }

  function shardCharge(type) {
    if (busy) return;
    var can = type === 'finish'
      ? !!(state && state.can_shard_finish)
      : (type === 'mining' ? !!(state && state.can_shard_mining) : !!(state && state.can_shard_weapon));
    if (!can) {
      setMsg(type === 'finish'
        ? '필살 횟수가 0이 아니거나 은총조각이 없어요.'
        : (type === 'mining'
          ? '채굴 공격횟수가 0이 아니거나 은총조각이 없어요.'
          : '무기 공격횟수가 0이 아니거나 은총조각이 없어요.'), 'err');
      return;
    }
    busy = true;
    var action = type === 'finish' ? 'shard_finish' : (type === 'mining' ? 'shard_mining' : 'shard_weapon');
    setMsg(type === 'finish'
      ? '은총조각으로 필살 횟수 초기화 중…'
      : (type === 'mining'
        ? '은총조각으로 채굴 횟수·내구 초기화 중…'
        : '은총조각으로 무기 횟수·내구 초기화 중…'), '');
    post(action).then(function (j) {
      if (j && j.ok) {
        render(j.state || j);
        setMsg(j.data || '충전 완료!', 'ok');
      } else {
        setMsg((j && j.data) ? j.data : '충전 실패', 'err');
        if (j && j.state) render(j.state);
      }
    }).catch(function () {
      setMsg('네트워크 오류', 'err');
    }).finally(function () {
      busy = false;
      drainRemoteFxQueue();
    });
  }
  var counterTabs = $('counterTabs');
  if (counterTabs) {
    counterTabs.addEventListener('click', function (e) {
      var btn = e.target && e.target.closest ? e.target.closest('.log-tab') : null;
      if (!btn) return;
      var tab = btn.getAttribute('data-tab') || 'all';
      if (tab === counterTab) return;
      counterTab = tab;
      var tabs = counterTabs.querySelectorAll('.log-tab');
      for (var i = 0; i < tabs.length; i++) {
        var on = tabs[i].getAttribute('data-tab') === counterTab;
        tabs[i].classList.toggle('active', on);
        tabs[i].setAttribute('aria-selected', on ? 'true' : 'false');
      }
      paintCounterList();
    });
  }

  var rulesFold = $('rulesFold');
  var rulesToggle = $('rulesToggle');
  if (rulesFold && rulesToggle) {
    try {
      if (localStorage.getItem('boss_rules_open') === '1') {
        rulesFold.classList.add('open');
        rulesToggle.setAttribute('aria-expanded', 'true');
      }
    } catch (e) {}
    rulesToggle.addEventListener('click', function () {
      var open = rulesFold.classList.toggle('open');
      rulesToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      try { localStorage.setItem('boss_rules_open', open ? '1' : '0'); } catch (e) {}
    });
  }

  var resultClose = $('resultClose');
  var resultOverlay = $('resultOverlay');
  if (resultClose) resultClose.addEventListener('click', hideResultLayer);
  if (resultOverlay) {
    resultOverlay.addEventListener('click', function (e) {
      if (e.target === resultOverlay) hideResultLayer();
    });
  }
  var btnReset = $('btnReset');
  if (btnReset) {
    btnReset.addEventListener('click', function () {
      if (busy || resultOpen) return;
      if (!confirm('대기 중인 보스를 지금 시작할까요?')) return;
      busy = true;
      post('reset').then(function (j) {
        if (j && j.ok) {
          render(j.state || j);
          setMsg(j.data || '보스 시작', 'ok');
        } else {
          setMsg((j && j.data) ? j.data : '실패', 'err');
        }
      }).catch(function () {
        setMsg('네트워크 오류', 'err');
      }).finally(function () { busy = false; });
    });
  }

  setInterval(function () {
    if (resultOpen) return;
    if (tickLeft > 0) {
      tickLeft -= 1;
      paintTimer();
      if (tickLeft <= 0) refreshState();
    }
  }, 1000);

  // 전투 중엔 lite 폴링(2.5초) · 대기 중 8초
  function pollLoop() {
    if (!bootReady || busy || resultOpen) {
      setTimeout(pollLoop, 400);
      return;
    }
    refreshState({ lite: true }).finally(function () {
      var ms = (state && state.phase === 'fighting') ? 2500 : 8000;
      setTimeout(pollLoop, ms);
    });
  }

  // 첫 진입: HTML에 심은 state 즉시 표시 · 백그라운드에서만 갱신
  (function bootFetch() {
    function applyBootTitle() {
      if (!state || !state.name) return;
      var t = state.phase === 'waiting'
        ? (state.need_admin ? '보스 · 출현 대기' : ('보스 · 다음 ' + state.name))
        : ('보스 · ' + (state.emoji ? (state.emoji + ' ') : '') + state.name);
      document.title = t;
    }
    function finishBoot() {
      bootReady = true;
      if (state) {
        rememberFightSnap(state);
        bootstrapHitCursor(state);
        applyBootTitle();
        setMsg('');
      }
      setTimeout(pollLoop, 2000);
    }
    var embedded = window.BOSS_BOOT && window.BOSS_BOOT.state;
    // null 은 typeof 'object' 이므로 phase 로 유효성 확인
    if (embedded && typeof embedded === 'object' && embedded.phase) {
      render(embedded);
      finishBoot();
      var p = (state.phase === 'fighting')
        ? refreshState()
        : refreshState({ lite: true });
      p.catch(function () {});
      return;
    }
    // 폴백: 문구 없이 조용히 갱신 (HTML 플레이스홀더 유지)
    refreshState({ lite: true }).then(function () {
      if (state) {
        if (state.phase === 'fighting') {
          return refreshState();
        }
      } else {
        setMsg('상태를 불러오지 못했어요. 새로고침 해주세요.', 'err');
      }
    }).then(function () {
      finishBoot();
    }).catch(function () {
      bootReady = true;
      if (!state) setMsg('네트워크 오류 · 다시 시도해 주세요.', 'err');
      setTimeout(pollLoop, 2000);
    });
  })();
})();
