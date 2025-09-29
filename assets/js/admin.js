/**
 * ZC DMT - Admin JS
 * - Copy-to-clipboard helpers for buttons with data-clip attribute
 * - Click to select code blocks
 */
(function($){
  $(function(){

    // Copy buttons (used in Indicators table actions, etc.)
    $(document).on('click', '.zc-copy-btn', function(e){
      e.preventDefault();
      var $btn = $(this);
      var text = $btn.attr('data-clip') || '';
      if (!text) return;

      function mark(stateOk) {
        var original = $btn.text();
        $btn.prop('disabled', true).text(stateOk ? 'Copied' : 'Copy failed');
        setTimeout(function(){
          $btn.prop('disabled', false).text(original);
        }, 1200);
      }

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function(){
          mark(true);
        }).catch(function(){
          // Fallback
          var ta = document.createElement('textarea');
          ta.value = text;
          document.body.appendChild(ta);
          ta.select();
          try {
            document.execCommand('copy');
            mark(true);
          } catch (err) {
            mark(false);
          }
          document.body.removeChild(ta);
        });
      } else {
        // Legacy fallback
        var ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        var ok = false;
        try { ok = document.execCommand('copy'); } catch (err) {}
        document.body.removeChild(ta);
        mark(!!ok);
      }
    });

    // Click to select any <code> blocks (helpful for REST base, etc.)
    $(document).on('click', '.notice code, code', function(){
      try {
        var range = document.createRange();
        range.selectNode(this);
        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
      } catch(e) {}
    });

    // ===== Shortcode Builder (Settings page) =====
    var cfg = window.zcDmtAdmin || {};
    var AJAX_URL = cfg.ajaxUrl || '';
    var NONCE = cfg.nonce || '';

    var $sel = $('#zc_sb_indicator');
    var $btnLoad = $('#zc_sb_load');
    var $slug = $('#zc_sb_slug');
    var $lib = $('#zc_sb_library');
    var $tf = $('#zc_sb_timeframe');
    var $height = $('#zc_sb_height');
    var $controls = $('#zc_sb_controls');
    var $out = $('#zc_sb_output');
    var $btnBuild = $('#zc_sb_build');
    var $btnCopy = $('#zc_sb_copy');
    var $btnTest = $('#zc_sb_test');
    var $msg = $('#zc_sb_msg');

    function setMsg(text, ok) {
      if (!$msg.length) return;
      $msg.text(text).css('color', ok ? '#155724' : '#721c24');
      if (text) {
        setTimeout(function(){ $msg.text(''); }, 3000);
      }
    }

    function adminAjax(action, payload) {
      return new Promise(function(resolve, reject){
        if (!AJAX_URL || !NONCE) {
          return reject(new Error('Missing AJAX config'));
        }
        var form = new FormData();
        form.append('action', action);
        form.append('nonce', NONCE);
        Object.keys(payload || {}).forEach(function(k){
          form.append(k, payload[k]);
        });
        fetch(AJAX_URL, { method: 'POST', body: form, credentials: 'same-origin' })
          .then(function(r){ if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
          .then(function(json){ resolve(json); })
          .catch(function(err){ reject(err); });
      });
    }

    function loadIndicators() {
      if (!$sel.length) return;
      $sel.html('<option value="">' + '-- Loading... --' + '</option>');
      adminAjax('zc_dmt_list_indicators', {})
        .then(function(json){
          if (!json || json.status !== 'success' || !Array.isArray(json.data)) {
            throw new Error('Invalid response');
          }
          var opts = ['<option value="">' + '-- Select indicator --' + '</option>'];
          json.data.forEach(function(ind){
            var name = ind.name || ind.slug || '';
            var slug = ind.slug || '';
            opts.push('<option value="' + slug.replace(/&/g,'&amp;').replace(/"/g,'"') + '">' + name.replace(/&/g,'&amp;').replace(/</g,'<') + ' (' + slug.replace(/&/g,'&amp;').replace(/</g,'<') + ')</option>');
          });
          $sel.html(opts.join(''));
          setMsg('Indicators loaded', true);
        })
        .catch(function(){
          $sel.html('<option value="">' + '-- Failed to load. Ensure you have indicators. --' + '</option>');
          setMsg('Failed to load indicators', false);
        });
    }

    function currentSlug() {
      var s = ($slug.val() || '').trim();
      if (s) return s;
      var v = ($sel.val() || '').trim();
      return v;
    }

    function buildShortcode() {
      var slugVal = currentSlug();
      if (!slugVal) {
        setMsg('Please select or enter an indicator slug', false);
        return;
      }
      var lib = ($lib.val() || 'chartjs').toLowerCase();
      var tf = ($tf.val() || '1y');
      var h = ($height.val() || '300px');
      var showCtrls = !!$controls.is(':checked');

      var parts = ['[zc_chart_dynamic'];
      parts.push('id="' + slugVal.replace(/"/g, '"') + '"');
      if (lib) parts.push('library="' + lib + '"');
      if (tf) parts.push('timeframe="' + tf + '"');
      if (h) parts.push('height="' + h + '"');
      parts.push('controls="' + (showCtrls ? 'true' : 'false') + '"');
      parts.push(']');

      var sc = parts.join(' ');
      $out.val(sc);
      setMsg('Shortcode built', true);
    }

    function fallbackCopy(text) {
      var ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      var ok = false;
      try { ok = document.execCommand('copy'); } catch (err) {}
      document.body.removeChild(ta);
      setMsg(ok ? 'Copied' : 'Copy failed', ok);
    }

    function copyOutput() {
      var text = $out.val() || '';
      if (!text) {
        setMsg('Nothing to copy', false);
        return;
      }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function(){
          setMsg('Copied', true);
        }).catch(function(){
          fallbackCopy(text);
        });
      } else {
        fallbackCopy(text);
      }
    }

    function testFetch() {
      var slugVal = currentSlug();
      if (!slugVal) {
        setMsg('Enter a slug to test', false);
        return;
      }
      adminAjax('zc_dmt_get_data', { slug: slugVal })
        .then(function(json){
          if (json && json.status === 'success' && json.data && Array.isArray(json.data.series)) {
            var len = json.data.series.length;
            setMsg('OK: received ' + len + ' point(s)', true);
          } else {
            setMsg('Invalid data response', false);
          }
        })
        .catch(function(err){
          setMsg('Fetch failed: ' + (err && err.message ? err.message : 'unknown'), false);
        });
    }

    if ($btnLoad.length) $btnLoad.on('click', loadIndicators);
    if ($sel.length) {
      $sel.on('change', function(){
        if (!$slug.val()) {
          var v = ($sel.val() || '').trim();
          $slug.val(v);
        }
      });
    }
    if ($btnBuild.length) $btnBuild.on('click', buildShortcode);
    if ($btnCopy.length) $btnCopy.on('click', copyOutput);
    if ($btnTest.length) $btnTest.on('click', testFetch);

    // ===== Indicators form: toggle source-specific fields =====
    var $sourceType = $('#zc_source_type');
    var $gsRow = $('#zc_google_sheets_row');
    var $fredRow = $('#zc_fred_row');
    var $wbRow = $('#zc_world_bank_row');
    var $dbnRow = $('#zc_dbnomics_row');
    var $euroRow = $('#zc_eurostat_row');
    var $oecdRow = $('#zc_oecd_row');
    var $ukRow = $('#zc_ukons_row');
    var $yahooRow = $('#zc_yahoo_row');
    var $googleRow = $('#zc_google_row');

    function toggleSourceRow(){
      if (!$sourceType.length) return;
      var val = ($sourceType.val() || '').toLowerCase();

      if ($gsRow.length) {
        $gsRow.toggle(val === 'google_sheets');
      }
      if ($fredRow.length) {
        $fredRow.toggle(val === 'fred');
      }
      if ($wbRow.length) {
        $wbRow.toggle(val === 'world_bank');
      }
      if ($dbnRow.length) {
        $dbnRow.toggle(val === 'dbnomics');
      }
      if ($euroRow.length) {
        $euroRow.toggle(val === 'eurostat');
      }
      if ($oecdRow.length) {
        $oecdRow.toggle(val === 'oecd');
      }
      if ($ukRow.length) {
        $ukRow.toggle(val === 'uk_ons');
      }
      if ($yahooRow.length) {
        $yahooRow.toggle(val === 'yahoo_finance');
      }
      if ($googleRow.length) {
        $googleRow.toggle(val === 'google_finance');
      }
    }

    if ($sourceType.length) {
      toggleSourceRow();
      $sourceType.on('change', toggleSourceRow);
    }
  });
})(jQuery);
