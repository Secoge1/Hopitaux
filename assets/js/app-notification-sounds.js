/**
 * Notifications sonores — assignation patient & messages communication.
 * Utilise Web Audio API (aucun fichier audio externe).
 */
(function (global) {
    'use strict';

    var cfg = global.APP_NOTIFICATION_SOUNDS || { enabled: true, modules: ['patients', 'communication'] };
    var audioCtx = null;
    var unlocked = false;

    function ensureContext() {
        if (!cfg.enabled) {
            return Promise.resolve(null);
        }
        if (!audioCtx) {
            var Ctx = global.AudioContext || global.webkitAudioContext;
            if (!Ctx) {
                return Promise.resolve(null);
            }
            audioCtx = new Ctx();
        }
        if (audioCtx.state === 'suspended') {
            return audioCtx.resume().then(function () {
                unlocked = true;
                return audioCtx;
            }).catch(function () {
                return null;
            });
        }
        unlocked = true;
        return Promise.resolve(audioCtx);
    }

    function tone(ctx, freq, start, duration, volume) {
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.value = freq;
        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(volume, start + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(start);
        osc.stop(start + duration + 0.05);
    }

    function playPatientAssignment(ctx) {
        var t = ctx.currentTime;
        tone(ctx, 523.25, t, 0.18, 0.22);
        tone(ctx, 659.25, t + 0.2, 0.22, 0.24);
        tone(ctx, 783.99, t + 0.42, 0.28, 0.2);
    }

    function playMessage(ctx) {
        var t = ctx.currentTime;
        tone(ctx, 880, t, 0.12, 0.18);
        tone(ctx, 1174.66, t + 0.14, 0.2, 0.16);
    }

    function playForModule(module) {
        if (!cfg.enabled) {
            return Promise.resolve();
        }
        var allowed = cfg.modules || [];
        if (allowed.indexOf(module) === -1) {
            return Promise.resolve();
        }
        return ensureContext().then(function (ctx) {
            if (!ctx) {
                return;
            }
            if (module === 'patients') {
                playPatientAssignment(ctx);
            } else if (module === 'communication') {
                playMessage(ctx);
            }
        });
    }

    function playForItems(items) {
        if (!items || !items.length) {
            return Promise.resolve();
        }
        var played = {};
        var chain = Promise.resolve();
        items.forEach(function (item) {
            var mod = item && item.module ? String(item.module) : '';
            if (!mod || played[mod]) {
                return;
            }
            played[mod] = true;
            chain = chain.then(function () {
                return playForModule(mod);
            });
        });
        return chain;
    }

    function unlockOnInteraction() {
        ensureContext();
    }

    document.addEventListener('click', unlockOnInteraction, { once: true, passive: true });
    document.addEventListener('keydown', unlockOnInteraction, { once: true, passive: true });

    global.AppNotificationSounds = {
        playForModule: playForModule,
        playForItems: playForItems,
        unlock: unlockOnInteraction,
        isEnabled: function () {
            return !!cfg.enabled;
        }
    };
})(window);
