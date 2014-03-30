<?php // JSON

    include_once "readconfig.php";
    $config = $config["mosaic"];

    /* Вернуть json-responce
     * attr[0]: успешный запрос или нет (success или failure)
     * attr[1]: сопутствующее сообщение
     * attr[2]: ответ как ассоциативный массив
     * note: функция прекращает работу скрипта через die()
     */
    function return_json($result, $message = null, $answear = null) {
        die(json_encode(array(
            "result"    => ($result ?"success" :"failure"),
            "message"   => $message,
            "answear"   => $answear,
        )));
    }

    // атрибуты запроса
    if (!$data = json_decode($_POST["jdata"], true)) 
        return_json(false, "Некорректные параметры запроса!", array("qnum" => 0));
    // проверка номер запроса
    if (!isset($data['qnum']))
        return_json(false, "Некорректные параметры запроса!", array("qnum" => 0));

    // сохранение номера запроса
    $answear = array(
        "qnum" => (int)$data['qnum']
    );

    // проверка кода запроса
    if (!isset($data['qcode'])) 
        return_json(false, "Не указан код выполняемого запроса!", $answear);

    // подключение к базе данных
    if (!mysql_connect($config['db_host'], $config['db_user'], $config['db_pass']))
        return_json(false, "Не удалось подключиться к серверу базы данных!", $answear);
    if (!mysql_select_db($config['db_base'])) 
        return_json(false, "Не найдена необходимая база данных!", $answear);
    mysql_query("SET names 'utf8'");

    // проверка наличия таблицы рекордов
    $mysql_result = mysql_query("SHOW TABLES");
    $tables = array();
    while ($row = mysql_fetch_row($mysql_result)) {
        array_push($tables, $row[0]);
    }
    if (!in_array($config["table_records"], $tables))
        return_json(false, "Не найдена таблица игровых рекордов!", $answear);

    // выполнение запроса
    switch ($data['qcode']) {

        // сохранить результат
        case 1:
            // проверка обязательных параметров
            if (!isset($data['pid']) || !isset($data['power']) || !isset($data['res_dur']) || !isset($data['res_steps']))
                return_json(false, "Недостаточно параметров для сохранения результата!", $answear);
            // выполнение запроса в базу данных
            $mysql_result = mysql_query("INSERT INTO " .$config["table_records"] ." ( rid, pid, power, player, time, res_dur, res_steps )
                VALUES ( NULL , '" .$data["pid"] ."', '" .$data["power"] ."', '" .$data["player"] ."', '" .$data["time"] ."', '" .$data["res_dur"] ."', '" .$data["res_steps"] ."' )");
            // проверка успешности запроса
            if (!mysql_insert_id())
                return_json(true, "Не удалось сохранить результат!", $answear);
            // успешный результат запроса
            return_json(true, "Результат успешно сохранен.", $answear);
            break;

        // получить рекорд
        case 2:
            // проверка обязательных параметров
            if (!isset($data['pid']) || !isset($data['power']))
                return_json(false, "Недостаточно параметров для получения рекорда!", $answear);
            // выполнение запроса в базу данных
            $mysql_result = mysql_query("SELECT * FROM " .$config["table_records"] ." WHERE pid='" .$data["pid"] ."' AND power='" .$data["power"] ."' ORDER BY res_dur ASC");
            $record = mysql_fetch_assoc($mysql_result);
            // формирование результата и возврат
            if ($record) {
                $answear["record"] = true;
                $answear["player"] = $record["player"];
                $answear["time"] = $record["time"];
                $answear["res_dur"] = $record["res_dur"];
                $answear["res_steps"] = $record["res_steps"];
            }
            else {
                $answear["record"] = false; 
            }
            return_json(true, "Рекорд успешно получен.", $answear);
            break;

        // получить позицию результата в таблице
        case 3:
            // проверка обязательных параметров
            if (!isset($data['pid']) || !isset($data['power']) || !isset($data['res_dur']))
                return_json(false, "Недостаточно параметров для получения рейтинга!", $answear);
            // выполнение запроса в базу данных
            $list = array();
            $mysql_result = mysql_query("SELECT * FROM " .$config["table_records"] ." WHERE pid='" .$data["pid"] ."' AND power='" .$data["power"] ."' ORDER BY res_dur ASC");
            while ($row = mysql_fetch_assoc($mysql_result))
                array_push($list, (double)$row["res_dur"]);
            // поиск позиции
            $data["res_dur"] = (double)$data["res_dur"];
            for ($i=0; $i!=count($list); ++$i)
                if ($data["res_dur"] < $list[$i]) break;
            // формирование результата и возврат
            $answear["pos"] = $i+1;
            $answear["all"] = count($list);
            $answear["diff"] = (count($list) > 0 ?($list[0] - $data["res_dur"]) :0.);
            return_json(true, "Рейтинг успешно получен.", $answear);
            break;

        // иначе неизвестный код запроса
        default:
            return_json(false, "Неизвестный код выполняемого запроса!", $answear);
            break;
    }

?>