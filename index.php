<!DOCTYPE html>
<html>

<?php
    // чтение конфигурационного файла
    include_once "prog/readconfig.php";
    $config = $config["mosaic"];
    
    // попытка установить соединение с базой данных
    // и проверка ее валидности
    if ($config["online"]) {
        $online = true;
        try {
            // установка соединения
            if (!mysql_connect($config["db_host"], $config["db_user"], $config["db_pass"]))
                throw new Exception("Database connection error!");
            if (!mysql_select_db($config["db_base"])) 
                throw new Exception("Database not found!");
            mysql_query("SET names 'utf8'");
            // проверка наличия необходимых таблиц
            $mysql_result = mysql_query("SHOW TABLES");
            $tables = array();
            while ($row = mysql_fetch_row($mysql_result)) {
                array_push($tables, $row[0]);
            }
            if (!in_array($config["table_pictures"], $tables))
                throw new Exception("Pictures table not found!");
            if (!in_array($config["table_records"], $tables))
                throw new Exception("Records table not found!");
        } 
        catch (Exception $e) {       
            //echo $e->getMessage();
            $online = false;
        }
    }
    else {
        $online = false;
    }
?>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Mosaic</title>
    <link rel="shortcut icon" type="image/png" href="img/icon.png">
    <link rel="stylesheet" type="text/css" href="css/mosaic.css">

    <script type="text/javascript" src="js/jquery.js"></script>
    <script type="text/javascript" src="js/jquery-ui-custom.js"></script>
    <script type="text/javascript" src="js/jquery-plugin.js"></script>

    <script type="text/javascript">
        /* Фабрика мозаики
         * attr[0]: используемый контейнер
         * attr[1]: настройки и опции
         * return: объект с интерфейсом
         */
        function Mosaic(container, option) {
            // Константы
            // Период срабатывания таймера
            var TACT_PERIOD = 1000;
            // размер изображения (пиксели)
            var PIC_SIZE = 400; 
            // минимальная степень квантования                                                
            var POWER_MIN = <?php echo $config["power_min"]; ?>;
            // максимальная степень квантования                 
            var POWER_MAX = <?php echo $config["power_max"]; ?>;
            // онлайновый режим игры или нет
            var ONLINE = <?php echo (int)$online; ?>;
            // таймаут выполнения ajax-запросов
            var AJAX_TIMEOUT = <?php echo $config["ajax_timeout"]; ?>;
            // порядковый номер выполняемого запроса
            var query_number = 0;

            // Настройки
            option = $.extend(true, {
                /* Функция при изменении расположения фрагментов
                 * attr[0]: атрибуты игры
                 * attr[1]: прогресс изображения
                 */
                update: function(attr, status) {
                },
                /* функция при завершении построения
                 * attr[0]: результаты игры
                 * attr[1]: онлайновая игра или нет (bool)
                 */
                built: function(result, online) {
                },
                /* функция при такте игрового времени
                 * attr[0]: прошедшее время (мс)
                 */
                ontact: function(passed) {
                }
            }, option);

            // инициализация изображения
            var image = new Image();
            // подготовка контейнера
            $(container).addClass("container");

            // Литерал игрового процесса
            var Game = {
                // атрибуты игры
                attr: {
                    // степень квантования изображения
                    power: 0,
                    // количество шагов
                    steps: 0,
                    // времени прошло
                    passed: 0,
                    // время начала и конца игры (мс)
                    start: undefined,
                    stop: undefined,
                },
                // регулярное выражение для проверки имени игрока
                namereg: "^[0-9a-zA-Z]{3,20}$",
                // дискриптор таймера
                timer: undefined,
                // начало игры
                start: function(power) {
                    var self = this;
                    // установка артибутов
                    self.attr = {
                        power: power,
                        steps: 0,
                        passed: 0,
                        start: new Date(),
                        stop: undefined,
                    };
                    // запуск таймера
                    if (typeof self.timer != "undefined") clearInterval(self.timer);
                    self.timer = setInterval(function() {
                        self.attr.passed += TACT_PERIOD;
                        if (typeof option.ontact == "function") {
                            option.ontact(self.attr.passed);
                        };
                    }, TACT_PERIOD);
                },
                // конец игры
                stop: function() {
                    if (typeof this.timer != "undefined") clearInterval(this.timer);
                },
                // следующий шаг
                step: function() {
                    this.attr.steps++;
                }
            }
            
            // Приватные методы ============================
            
            /* Выполнение ajax-запроса
             * attr[0]: атрибуты запроса
             * attr[1]: функции выполняемые (success, failure)
             * attr[2]: отбрасывать запаздавшие ответы или нет (bool)
             */
            function ajax_request(attr, callback, drop) {
                if (typeof drop == "undefined") drop = false;
                // атрибуты запроса
                attr = $.extend(true, {
                    qnum: ++query_number
                }, attr);
                // выполнение запроса
                $.ajax({ 
                    url: "prog/recquery.php", 
                    type: "POST", 
                    dataType: "json", 
                    data: "jdata=" +JSON.stringify(attr), 
                    cache: false, 
                    timeout: AJAX_TIMEOUT,
                    success: function(jres) {
                        // обработать запоздавшие ответы
                        if (jres.answear.qnum == query_number || !drop) {
                            console.log("comp");
                            // если запрос выполнился успешно, то передать результат на обработку
                            if (jres.result == "success") {
                                if (typeof callback.success == "function")
                                    callback.success(jres);
                            }
                            // иначе вызвать функцию аварийного возврата
                            else {
                                if (typeof callback.failure == "function")
                                    callback.failure(jres.message);
                            }
                        }
                    },
                    error: function() {
                        // вывод ошибки в консоль как исключение
                        exception("Interval server error, query code " + attr.qcode + "!", true);
                        // вызов функции критического возврата
                        if (typeof callback.failure == "function")
                            callback.failure("Истекло время ожидания ответа от сервера!\nСкорее всего некая ошибка на стороне сервера.");
                    }
                });
            }

            /* Вывод в консоль исключения
             * attr[0]: текст сообщения
             * attr[1]: критическая ошибка или нет (bool)
             */
            function exception(msg, error) {
                if (error) console.error("Mosaic: " + msg);
                else console.warn("Mosaic: " + msg);
            }
            /* Проверить рабочее поле на изменения
             * attr[0]: при инициализации или нет (bool)
             * return: количество правильных фрагментов
             * note: использует функцию option.update
             */
            function update(init) {
                /* Получить текущий прогресс сборки
                 * return: количество правильных и всех фрагментов 
                 */
                function progress() {
                    var list = $(container).find(".sortable:first LI"),
                        amount = 0;
                    for (var i=0, max=list.length; i!=max; ++i)
                        if ($(list[i]).attr("data-number") == i) amount++;
                    return {
                        cur: amount,
                        max: max
                    }
                };
                /* Изображение собрано
                 * note: использует функцию option.built
                 */
                function built() {
                    show();
                    // сохранение времени игры
                    Game.attr.stop = new Date();
                    // формирование разультата
                    var result = {
                        pid: image.pid,
                        pic_name: image.name,
                        power: Game.attr.power,
                        res_dur: Math.round((Game.attr.stop - Game.attr.start)/10)/100,
                        res_steps: Game.attr.steps
                    }
                    // выполнение функции
                    if (typeof option.built == "function") {
                        option.built(result, ONLINE);
                    }
                }

                // получить статус
                var status = progress();
                // если не инициализация, то обработать статус
                if (!init) {
                    // следующий шаг
                    Game.step();
                    // вызов коллбека
                    if (typeof option.update == "function") {
                        option.update(Game.attr, status);
                    }
                    // если изображение собрано
                    if (status.cur == status.max) {
                        built();
                    }
                }
                // вернуть статус
                return status.cur;
            };
            /* Разбить изображение
             * attr[0]: степень квантования изображения
             * attr[1]: смешивать или нет (bool)
             */
            function picbreak(power, mix) {
                /* Сформировать фрагмент изображения
                 * attr[0]: порядковый номер фрагмента
                 * attr[1]: смещение по абсцисс в пикселях
                 * attr[2]: смещение по ординат в пикселях
                 * attr[3]: размер фрагмента в пикселях
                 * return: HTML-код нового фрагмента
                 */
                function piece(n, x, y, size) {
                    var PIECE_BORDER = 2;
                    return "<li data-number='" + n + "' style=\"width:" + (size-2*PIECE_BORDER) 
                        + "px; height:" + (size-2*PIECE_BORDER) + "px;\">\
                        <img class='img_piece' src='" + image.src +"'\
                            style=\"clip:rect(" + (y+PIECE_BORDER) +"px, " + (x+size-PIECE_BORDER) + "px, " 
                                + (y+size-PIECE_BORDER) + "px, " + (x+PIECE_BORDER) +"px); left: " 
                                + (-1*x-PIECE_BORDER) + "px; top: " + (-1*y-PIECE_BORDER) + "px; \" /></li>";
                };
                /* Добавление сортировки
                 * note: используется jQuery UI
                 */
                function sortinit() {
                    $(container).find("UL:first").sortable({
                        tolerance: 'pointer',
                        forcePlaceholderSize: true,
                        placeholder: "placeholder",
                        update: function(event, ui) {
                            update(false);
                        }
                    }).disableSelection();
                };
                /* Перемешать фрагменты изображения
                 * note: используется плагин jQuery shuffle
                 */
                function mixer() {
                    do {
                        $(container).find(".sortable:first LI").shuffle();
                    } while (update(true));
                };

                // разрезание изображения
                var size = Math.floor(PIC_SIZE/power);
                var container_list = $(container).find("UL:first");                  
                for (var i=0; i!=power; ++i) {
                    for (var j=0; j!=power; ++j)
                        $(container_list).append(piece(i*power+j, j*size, i*size, size));
                };
                // добавление сортировки и перетасовка
                sortinit();
                if (mix) mixer();
            }
            /* Вывести исходное изображение */
            function show() {
                Game.stop();
                $(container).empty().append("<img src='" + image.src + "' />"); 
            };

            // Интерфейс ===================================

            return {
                /* Установить путь к изображению и показать его
                 * attr[0]: описание изображения (путь, название, id)
                 * return: проверка пути (bool)
                 */
                image: function(img) {
                    // если новое изображение, то поменять путь
                    if (image.src != img.src) {
                        image.src = img.src;
                        image.name = img.name;
                        image.pid = (typeof img.pid != "undefined" 
                            ?parseInt(img.pid) :undefined);
                    }
                    // проверка изображения
                    if (!image.src) {
                        exception("Invalid image path!", true);
                        return false;
                    }
                    else {
                        // показать изображение
                        show();
                        return true;
                    }
                },
                /* Разбить изображение на фрагменты
                 * attr[0]: степень квантования (целое > 2)
                 * return: откорректированное значение степени (при необходимости)
                 */
                split: function(power) {
                    // если степень не определена или меньше минимума, то присвоить минимум
                    if (isNaN(power) || (typeof power != "number") || (power < POWER_MIN)) {
                        power = POWER_MIN;
                        exception("Invalid power, was changed to min " + power + ".", false);
                    }
                    // иначе если степень больше максимума, то присвоить максимум
                    else if (power > POWER_MAX) {
                        power = POWER_MAX;
                        exception("Invalid power, was changed to max " + power + ".", false);
                    };
                    // проверка изображения
                    if (!image.src) {
                        exception("Invalid image path!", true);
                    }
                    else {
                        // подготовка контейнера
                        $(container).empty().append("<ul class='sortable'></ul>");
                        // разрезать и смешать изображение
                        picbreak(power, true);
                        // запуск игры
                        Game.start(power);
                    }
                    // вернуть количество
                    return power;
                },
                /* Показать изображение */
                cancel: function() {
                    // проверка изображения
                    if (!image.src) {
                        exception("Invalid image path!", true);
                    } 
                    else {
                        // показать изображение
                        show();
                    }
                },
                /* Проверить имя игрока
                 * attr[0]: имя игрока
                 * return: корректное или нет (bool)
                 */
                isplayer: function(player) {
                    return RegExp(Game.namereg).test(player);
                },
                /* Отправить результат на сервер
                 * attr[0]: имя игрока
                 * attr[1]: выполняемые функции (success, failure)
                 */
                recquery_commit: function(player, callback) {
                    // выполняемые функции
                    callback = $.extend(true, {
                        /* Функция при успехе
                         * attr[0]: json-request
                         */
                        success: function(jres) {
                        },
                        /* Функция при неудаче 
                         * attr[0]: текст ошибки
                         */
                        failure: function(msg) {
                        }
                    }, callback);
                    // проверка атрибутов игры: завершение и id изображения
                    if ((typeof Game.attr.stop == "undefined") || (typeof image.pid == "undefined")) {
                        exception("Invalid game attributes!", true);
                        if (typeof callback.failure == "function") {
                            callback.failure("Некорректные атрибуты игры!");
                        }
                        return false;
                    }
                    // проверка имени игрока
                    if (!this.isplayer(player)) {
                        exception("Invalid player name '" + player + "'!", true);
                        if (typeof callback.failure == "function") {
                            callback.failure("Некорректное имя игрока!");
                        }
                        return false;
                    }
                    // формирование параметров запроса
                    var attr = {
                        qcode: 1,
                        pid: image.pid,
                        power: Game.attr.power,
                        res_dur: Math.round((Game.attr.stop - Game.attr.start)/10)/100,
                        res_steps: Game.attr.steps,
                        time: Game.attr.stop.toLocaleString(),
                        player: player
                    }
                    // выполнение запроса
                    ajax_request(attr, callback);
                },
                /* Определить место в таблице рекордов 
                 * attr[0]: выполняемые функции (success, failure)
                 */
                recquery_status: function(callback) {
                    // выполняемые функции
                    callback = $.extend(true, {
                        /* Функция при успехе
                         * attr[0]: json-request
                         */
                        success: function(jres) {
                        },
                        /* Функция при неудаче 
                         * attr[0]: текст ошибки
                         */
                        failure: function(msg) {
                        }
                    }, callback);
                    // проверка атрибутов игры: завершение и id изображения
                    if ((typeof Game.attr.stop == "undefined") || (typeof image.pid == "undefined")) {
                        exception("Invalid game attributes!", true);
                        if (typeof callback.failure == "function") {
                            callback.failure("Некорректные атрибуты игры!");
                        }
                        return false;
                    }
                    // формирование параметров запроса
                    var attr = {
                        qcode: 3,
                        pid: image.pid,
                        power: Game.attr.power,
                        res_dur: Math.round((Game.attr.stop - Game.attr.start)/10)/100,
                    }
                    // выполнение запроса
                    ajax_request(attr, callback);
                },
                /* Получить рекордсмена для изображения
                 * attr[0]: id изображения
                 * attr[1]: уровень сложности (степень квантования)
                 * attr[2]: выполняемые функции (success, failure)
                 */
                recquery_record: function(pid, power, callback) {
                    // выполняемые функции
                    callback = $.extend(true, {
                        /* Функция при успехе
                         * attr[0]: json-request
                         */
                        success: function(jres) {
                        },
                        /* Функция при неудаче 
                         * attr[0]: текст ошибки
                         */
                        failure: function(msg) {
                        }
                    }, callback);
                    // формирование параметров запроса
                    var attr = {
                        qcode: 2,
                        pid: pid,
                        power: power,
                    }
                    // выполнение запроса
                    ajax_request(attr, callback, true); 
                }
            }
        }
    </script>

    <script type="text/javascript">
        /* Логика страницы */
        (function(){
            // Инициализация
            // номер изображения в превью, выбираемого по умолчанию
            var picture_def = <?php echo $config["picture_def"]; ?>;
            // закрывающее изображение
            var pazle = new Image();
            pazle.src = "img/idea.png";

            /* Функция вывода сообщений
             * attr[0]: текст сообщения
             */
            function message(msg) {
                alert(msg);
            }

            // Загрузка страницы завершена
            $(document).ready(function(){
                /* Прогресс бар
                 * attr[0]: DOM-элемент прогресс бара
                 * attr[1]: значение процента
                 */
                function ProgressBar(bar, value) {
                    var max = $(bar).width();
                    if (value > max) value = max;
                    $(bar).find(".input_div_bar:first").width(Math.round(max*value/100));
                };
                /* Установить статус игровго процесса (интерфейс)
                 * attr[0]: код состояния
                 */
                function GameStatus(gcode) {
                    switch (gcode) {
                        // во время игры
                        case 1: 
                            $("#control INPUT[name='size']").attr("readonly", true);
                            $("#control INPUT[name='mixer']").prop("disabled", true);
                            $("#control INPUT[name='show']").prop("disabled", false);
                            break;
                        // ожидание
                        case 2:
                            $("#control INPUT[name='timer']").val("0");
                            $("#control INPUT[name='step']").val("0");
                            ProgressBar($("#control DIV[name='status']"), 0);
                            $("#control INPUT[name='size']").attr("readonly", false);
                            $("#control INPUT[name='mixer']").prop("disabled", false);
                            $("#control INPUT[name='show']").prop("disabled", true);
                            break;
                        // критическое состояние
                        default:
                            $("#control INPUT[name='size']").attr("readonly", true);
                            $("#control INPUT[name='mixer']").prop("disabled", true);
                            $("#control INPUT[name='show']").prop("disabled", true);
                            ProgressBar($("#control DIV[name='status']"), 0);
                            break;
                    }
                }

                // Создание мозаики
                var mos = Mosaic($("#picture"), {
                    update: function(attr, status) {                      
                        ProgressBar($("#control DIV[name='status']"), Math.round(100*status.cur/status.max));
                        $("#control INPUT[name='step']").val(attr.steps);
                    },
                    built: function(result, online) {
                        openResultWindow(result, online);                       
                    },
                    ontact: function(passed) {
                        $("#control INPUT[name='timer']").val(Math.round(passed/1000));
                    }
                });
               
                /* Инициализация изображения мозаики
                 * event: выбор изображения в превью
                 */
                $(document).on("click", "#picset LI", function(e) {
                    // если изображение еще не выбрано
                    if (!$(this).hasClass("li_active")) {
                        // очистить выбор
                        $(this).parent().find(".li_active").each(function(){
                            $(this).removeClass("li_active").find("IMG[data-type='lock']").remove();
                        });
                        // выбрать элемент
                        $(this).addClass("li_active").append("<img data-type='lock' src='" +pazle.src + "' />");
                        // поиск картинки
                        var image = $(this).find("IMG[data-type='picture']");
                        $("#record H2[name='name']").text(image.attr("data-name"));
                        // эмулировать изменение уровня для подстройки
                        $("#control INPUT[name='size']").change();
                        // инициализация изображения мозаики и игры
                        GameStatus(mos.image({
                            src: image.attr("src"), 
                            name: image.attr("data-name"),
                            pid: image.attr("data-pid")
                        }) ?2 :3);  
                    };
                });
                /* Разбить изображение на фрагменты
                 * event: нажатие клавиши "Старт"
                 */
                $(document).on("click", "#control INPUT[name='mixer']", function(e) {
                    GameStatus(1);
                    // фикс зажатой клавиши ввода в поле степени квантования
                    $("#control INPUT[name='size']").keyup();
                    // получить степень квантования
                    var power = parseInt($("#control INPUT[name='size']").val());
                    $("#control INPUT[name='size']").val(mos.split(power));
                });
                /* Показать исходное изображение
                 * event: нажатие клавиши "Сдаться"
                 */
                $(document).on("click", "#control INPUT[name='show']", function(e) {
                    GameStatus(2);
                    mos.cancel();
                });
                /* Запрос рекорда для изображения 
                 * event: изменилась степени квантования
                 */
                $(document).on("change", "#control INPUT[name='size']", function(e) {
                    // если удалось определить строку рекорда, то игра онлайновая и продолжить
                    var record_h = $("#record H3[name='record']").get();
                    if (record_h.length) {
                        // если удалось определить id изображения то продолжить
                        var pic = $("#picset LI.li_active IMG[data-type='picture']").attr("data-pid");
                        if (pic) {
                            var power = parseInt($(this).val());
                            $(record_h).html("Рекорд: ждите...");
                            mos.recquery_record(pic, power, {
                                success: function(jres) {
                                    var text = "Рекорд: ";
                                    if (jres.answear.record) {
                                        text += jres.answear.player + "<br />";
                                        text += jres.answear.time + "<br />";
                                        text += jres.answear.res_dur + " секунд, " + jres.answear.res_steps + " шагов";
                                    }
                                    else {
                                        text += "не утановлен";
                                    }
                                    $(record_h).html(text);
                                },
                                failure: function(msg) {
                                    $(record_h).html("Рекорд:<br />не удалось определить");
                                }
                            });
                        }
                    }
                });

                /* Функция статуса окна результатов
                 * attr[0]: активно управление или нет (bool)
                 */
                function activeResultWindow(active) {
                    $("#result INPUT[name='accept']").prop("disabled", !active);
                    $("#result INPUT[name='close']").prop("disabled", !active);
                    $("#result INPUT[name='player']").attr("readonly", !active);
                }
                /* Функция открытия окна результатов
                 * attr[0]: результаты
                 * attr[1]: онлайнова игра или нет (bool)
                 */
                function openResultWindow(result, online) {                   
                    // заполнение параметров
                    $("#result H2[name='game_name']").text(result.pic_name);
                    $("#result TD[name='game_power']").text(result.power);
                    $("#result TD[name='game_result']").text(result.res_dur + " секунд, " 
                        + result.res_steps + " шагов");
                    if (online) {
                        $("#result TD[name='game_status']").html("ждите...");
                        mos.recquery_status({
                            success: function(jres) {
                                var text = "";
                                if (jres.answear.all == 0) {
                                    text += "первая игра";
                                } 
                                else {
                                    text += (jres.answear.pos == 1 
                                        ?"Лидер из " :jres.answear.pos + " место из ") + (jres.answear.all + 1);
                                    text += " (" + (jres.answear.diff >= 0 
                                        ?"+" +jres.answear.diff :jres.answear.diff) + ")";
                                }
                                $("#result TD[name='game_status']").html(text);
                            },
                            failure: function(msg) {
                                $("#result TD[name='game_status']").html("не удалось определить");
                            }
                        });
                    }
                    // открыть окно                  
                    $("#mask").fadeIn(400);
                    $("#result").fadeIn(200, function() {
                        // настройки окна
                        activeResultWindow(true);
                        $("#result INPUT[name='player']").keyup();
                    });
                }
                /* Функция закрытия результатов */
                function closeResultWindow() {
                    // настройки окна
                    activeResultWindow(false);
                    GameStatus(2);
                    // закрыть окно
                    $("#mask").fadeOut(400);
                    $("#result").fadeOut(400);
                }

                /* Ввод имени пользователя
                 * event: ввод в поле имени пользователя
                 */
                $(document).on("keyup", "#result INPUT[name='player']", function(e) {
                    var value = $(this).val();
                    // если имя корректно, то отркыть доступ к кнопке
                    if (mos.isplayer(value)) {
                        $("#result INPUT[name='accept']").prop("disabled", false);
                        if ($(this).hasClass("inerror"))
                            $(this).removeClass("inerror");
                    }
                    // иначе закрыть доступ
                    else {
                        $("#result INPUT[name='accept']").prop("disabled", true);
                        if (!$(this).hasClass("inerror"))
                            $(this).addClass("inerror");
                    }
                });
                /* Закрыть окно результатов
                 * event: нажата клавиша "закрыть"
                 */
                $(document).on("click", "#result INPUT[name='close']", function(e) {
                    closeResultWindow();
                });
                /* Отправить результат
                 * event: нажата клавиша "принять"
                 */
                $(document).on("click", "#result INPUT[name='accept']", function(e) {
                    // проверка зажатия клавиши
                    $("#result INPUT[name='player']").keyup();
                    if (!$("#result INPUT[name='player']").hasClass("inerror")) {                       
                        // настройки окна
                        activeResultWindow(false);
                        // получить имя пользователя
                        var player = $("#result INPUT[name='player']").val();
                        // выполнить запрос
                        mos.recquery_commit(player, {
                            success: function(jres) {
                                //message(jres.message);
                                $("#control INPUT[name='size']").change();
                                closeResultWindow();
                            },
                            failure: function(msg) {
                                activeResultWindow(true);
                                if (typeof msg != "undefined")
                                    message("Ошибка: " + msg);
                            }
                        });                     
                    }
                });
                
                // Начальные настройки после загрузки
                // корректировка поля ввода степени квантования
                $("#control INPUT[name='size']").keyup();
                // если в списке есть изображения, то выбрать
                if ($("#picset LI").length) {
                    GameStatus(2);
                    $("#picset LI").eq($("#picset LI").length > picture_def ?picture_def :0).click();
                }
                // иначе ошибка
                else {
                    GameStatus(3);
                    message("Не удалось определить ни одного изображения!\nИгра невозможна.");
                }
            });
        })();
    </script>
</head>

<body>
    <!-- Главное окно приложения -->
    <div id='widget' class='window'>
        <div id='header' class='winhead'>
            <?php 
                //echo "<ul><li name='but_about'>A</li><li name='but_help'>H</li><li name='but_stat'>S</li><li name='but_source'>C</li></ul>";
                echo "<h4 class='status_" .($online ?"online'>Online" :"offline'>Offline") ."</h4>";
            ?>
        </div>
        <div id='picture' class='wform'></div>
        <div id='control' class='wform'>
            <div id='record'>
                <h2 name='name'>Название</h2>
                <?php if ($online) echo "<h3 name='record'>Рекорд:<br />не удалось определить</h3>"; ?>
            </div>
            <table>
                <tr><td>Уровень:</td><td><input name='size' tabindex='-1' value='<?php echo $config["power_def"]; ?>'></input></td></tr>
                <script type="text/javascript">
                    (function(){
                        $("#control INPUT[name='size']").numeric({
                            min: <?php echo $config["power_min"]; ?>,
                            max: <?php echo $config["power_max"]; ?>,
                            nan: <?php echo $config["power_min"]; ?>
                        });
                    })()
                </script>
                <tr><td>Старт:</td><td><input name='mixer' tabindex='-1' type='button' value='Перемешать'></input></td></tr>
                <tr><td>Время:</td><td><input name='timer' tabindex='-1' value='0' readonly></input></td></tr>
                <tr><td>Шаги:</td><td><input name='step' tabindex='-1' value='0' readonly></input></td></tr>
                <tr><td>Статус:</td><td><div class='input_div' name='status'><div class='input_div_bar'></div></div></td></tr>
                <tr><td>Сдаться:</td><td><input name='show' tabindex='-1' type='button' value='Показать'></input></td></tr>
            </table>
        </div>
        <div id='picset' class='wform'><ul>
        <?php
            /* Получить название изображения
             * attr[0]: путь к изображению
             * return: название изображения (без расширения)
             */
            function picname($path) { 
                $name = explode(".", basename($path));
                array_pop($name);
                return implode(".", $name);
            }
            /* Вывод изображения
             * attr[0]: путь к изображению
             * attr[1]: название изображения
             */
            function picoutput($path, $name, $pid = null) {
                if (filetype($path) == "file" && preg_match("/.(png|jpg|bmp)/i", $path)) {    
                    echo "<li><img data-type='picture' " .($pid !== null ?"data-pid='".$pid."' " :"") ."data-name='" .$name ."' src='" .$path ."' /><div class='holder'></div></li>";
                }
            }
            // если есть соединение с базой данных, то обратиться к ней
            if ($online) {
                $mysql_result = mysql_query("SELECT * FROM ". $config["table_pictures"] ." WHERE pic_turn='1' ORDER BY pid ASC");
                while ($row = mysql_fetch_assoc($mysql_result)) {
                    picoutput($row["pic_path"], $row["pic_name"], $row["pid"]);  
                }
            }
            // получить изображения с каталога
            else {
                $files = scandir("img/set");
                foreach ($files as $index => $path) {
                    $path = "img/set/" .$path;
                    picoutput($path, picname($path));
                }
            }
        ?>
        </ul></div>
        <script type="text/javascript">
            (function(){
                var width = $("#picset LI").length * ($("#picset LI:first").width() + 8) + 4;
                $("#picset UL").width(width);
            })()           
        </script>
    </div>
    <!-- Модальное окно результатов -->
    <div id='result' class='window'>
        <div class='winhead'><h2>Результат</h2></div>
        <div class='wform' id='player'><table>
            <tr><td colspan='2'><h2 name='game_name'>Название</h2></td></tr>
            <tr><td>уровень:</td><td name='game_power'>уровень</td></tr>
            <tr><td>результат:</td><td name='game_result'>результат</td></tr>
            <?php
                if ($online) {
                    echo "<tr><td>рейтинг:</td><td name='game_status'>не удалось определить</td></tr>";
                    echo "<tr><td>Игрок:</td><td><input name='player' placeholder='имя' tabindex='-1' autocomplete='off' value='Gamer'></input></td></tr>";
                }
            ?> 
        </table></div>
        <?php if ($online) echo "<input type='button' name='accept' tabindex='-1' value='Принять'></input>"; ?>
        <input type='button' name='close' tabindex='-1' value='Закрыть'></input>
    </div>
    <!-- Маска для модального окна -->
    <div id='mask'></div>
    <!-- О программе -->
    <h5>Mosaic v1.0.0<br />by cerberus.ab, 31.03.14</h5>
</body>

</html>