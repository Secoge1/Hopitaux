(function (global) {
    'use strict';

    function config() {
        return global.APP_CURRENCY || {
            code: 'XOF',
            symbol: 'FCFA',
            decimals: 0,
            conversion: false,
            base: 'XOF',
            rateFromBase: 1,
        };
    }

    function displayAmount(amountInXOF) {
        var c = config();
        var v = Number(amountInXOF) || 0;
        if (c.conversion && c.code !== c.base) {
            v = v * (Number(c.rateFromBase) || 1);
        }
        return v;
    }

    function appFormatMoney(amountInXOF, showSymbol) {
        if (showSymbol === undefined) {
            showSymbol = true;
        }
        var c = config();
        var v = displayAmount(amountInXOF);
        var formatted = v.toLocaleString('fr-FR', {
            minimumFractionDigits: c.decimals,
            maximumFractionDigits: c.decimals,
        });
        return showSymbol ? (c.symbol + ' ' + formatted) : formatted;
    }

    global.appFormatMoney = appFormatMoney;
    global.appCurrencyLabel = function () {
        return config().symbol;
    };
})(window);
