/* Плагин перетасовки элементов
 * return: перетасованный набор элементов
 */
(function($) {
    $.fn.shuffle = function() {
        var allElems = this.get();
        // случайное число
        var getRandom = function(max) {
            return Math.floor(Math.random() * max);
        };
        // перетасовка элементов
        var shuffled = $.map(allElems, function() {
            var random = getRandom(allElems.length),
                randEl = $(allElems[random]).clone(true)[0];
            allElems.splice(random, 1);
            return randEl;
        });
        this.each(function(i) {
            $(this).replaceWith($(shuffled[i]));
        });
        // возврат набора элементов
        return $(shuffled);
    };
})($);
/* Плагин форматированного input 
 * return: набор форматированных элементов
 */
(function($) {
    $.fn.numeric = function(option) {
        // параметры инициализации
        option = $.extend(true, {
            // минимальное значение
            min: 0,
            // максимальное значение
            max: 10,
            // формировать плейсхолдер
            placeholder: true,
            // значение при NaN
            nan: "",
            // изменить значение при корректировке
            change: true
        }, option);
        // функция проверки
        function check() {
            var value = parseInt($(this).val()),
                min = parseInt($(this).attr("data-min")),
                max = parseInt($(this).attr("data-max"));
            var new_value = isNaN(value) ? option.nan : (value < min ? min : (value > max ? max : value));
            $(this).val(new_value);
            if (new_value != value && option.change) $(this).change();
        }
        // получить набор DOM элементов
        var allElems = this.get();
        // для каждого
        $(allElems).each(function() {
            // установить атрибуты
            $(this).attr("data-min", option.min).attr("data-max", option.max);
            if (option.placeholder)
                $(this).attr("placeholder", option.min + ".." + option.max);
            // корректировка ввода
            $(this).keyup(function(e) {
                check.call(this);
            });
            $(this).focusout(function(e) {
                check.call(this);
            });
        });
        // вернуть набор
        return $(allElems);
    };
})($);