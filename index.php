<?php
header("Content-Type:text/html;charset=UTF8");
# Начинаем сесиию, что б туда сохранять наш массив гет запросов групп
session_start();


class TreeMain {

    public function __construct() {
        #подключаемся к БД
        $db = new PDO("mysql:dbname=maksimes;host=test1.loc", "maksimes", "q987654323");
        #запрашиваем все данные из таблицы групп
        $groups_q = $db->query("SELECT * FROM groups");
        #сохраняем их в массив, что б в дальнейшем ходит по массиву, а не делать бесконечное число запросов в БД
        $this->groups = $groups_q->fetchAll();
        #то же самое с таблицей товаров
        $products_q = $db->query("SELECT * FROM products"); 
        $this->products = $products_q->fetchAll();
        #в принципе можно было эти массивы сохранить в сессию и при перемещении по категориям не делать запросы в БД
        #но так как БД не столь огромная, запросов в целом получается не много, за то всегда актуальная информация о товарах и их группах

        #сюда мы будем сохранять группу на которую нажали и всех её потомков для вывода в дереве
        $this->child_groups_arr=array();
        #в пременную сохраняем результат функции поиска потомков по id группы из гет массива
        $this->child_result=$this->child_groups($_GET['group'], $child_groups_arr);

        echo '<a href="?products='."all".'">'."Все товары".'</a>' . "</br>";
        #выводим все продукты при нажатии на кнопку "все товары" и очищаем массив с открытими категориями в сессии
        if($_GET['products']) {
             $_SESSION['get_groups_arr'] = array(0);
             $this->child_result= array();
             foreach ($this->groups as $group) {
                $this->child_result[]=$group[id];
             }
        }

        #проверяем есть ли массив ггрупп в сессии ,если есть то копируем его в переменную, если нет нет, то создаем этот массив
        if($_SESSION['get_groups_arr']) {
            $get_groups_arr = $_SESSION['get_groups_arr'];
        } else {
            $get_groups_arr = array(0);
            $_SESSION['get_groups_arr'] = $get_groups_arr;
        }
        #если уже есть гет запрос с id группы, ищем id в массиве и удаляем из массива группу и всех её потомков
        if(isset($_GET['group'])) {
            if(array_search($_GET['group'], $get_groups_arr)) {
                foreach ($get_groups_arr as $value) {
                    if (in_array($value, $this->child_result)) {
                        unset($get_groups_arr[array_search($value, $get_groups_arr)]);
                    }
                }         
            } else {
                foreach ($this->groups as $group) {
                if($group[id] == $_GET['group']) {
                    foreach ($this->groups as $group_sibling) {
                        if($group_sibling[id_parent] == $group[id_parent]) {
                            unset($get_groups_arr[array_search($group_sibling[id], $get_groups_arr)]);
                        }
                    }
                }
            }
            #если такого id нет, то добавляем его в массив
            $get_groups_arr[]=$_GET['group'];
            }
            #сохраняем массив в сессию, что б при клике на другую категорию можно было снова к нему обратиться
            $_SESSION['get_groups_arr']=$get_groups_arr;

        }
    }

    #тут вы выводим дерево категорий
    public function tree($id_parent=0, $level=0) {
        foreach ($this->groups as $group) {
            if ($group[id_parent]==$id_parent) {
                #очищаем массив, где считаем количество товаров в каждой категории
                $this->child_groups_for_nums=array();
                #ну и вывод в виде списка
                #level нам нужен для того, что б ставить отступ для подкатегорий
                echo "<lu>";
                echo "<div style='margin-left:" . ($level * 25) . "px;'>" . "<li>" . '<a href="?group=' . $group[id] . '">' . $group[name] . ' </a>' . $this->nums_prod_in_groups($group[id]) . "</li>" . "</div>";
                    #если группа есть в массиве в нашей сессии, запускаем рекурсию, что б вывести в дерево с отступом её детей
                    if (array_search($group[id], $_SESSION['get_groups_arr'])) {
                        $level ++;
                        $this->tree($group[id], $level);
                        $level --;
                    }
                echo "</lu>";
            }
        }
    }

    #считаем количесвто товаров в каждой категории и во всех её подкатегориях
    public function nums_prod_in_groups($group_id_prod, $count=0) {
        #сохраняем в переменную массив с нужной группой и всеми её потомками
        $child_groups = $this->child_gr_num_prod($group_id_prod, $this->child_gr_num_prod);
        #ищем продукты, где id группы совпадает с теми, что у нас в массиве потомков
        foreach ($child_groups as $c_group) {
            foreach ($this->products as $product) {
                if($c_group == $product[id_group]) {
                    #добавляем +1 продукт, если нашли
                    $count++;
                }
            }
        }
        return $count;
    }

    #тут собираем детей групп для вывода дерева подкатегорий
    public function child_groups($group_id, $child_groups_arr) {
        #если в массиве нет группы, добавляем в массив саму нашу группу, а в далее в рекурсии и потомков
        if (!in_array($group_id, $this->child_groups_arr)) {
            $this->child_groups_arr[] = $group_id;
        }
        foreach ($this->groups as $group) {
            #ищем нашу группу по id в массиве групп
            if ($group[id_parent] == $group_id) {
                if (!in_array($group[id], $this->child_groups_arr)) {
                    #если у группы есть ребенок, то с ним запускаем рекурсию
                    $this->child_groups_arr[] = $group[id];
                }
                $this->child_groups($group[id], $this->child_groups_arr);
            }
        }
        #возвращаем массив групп потомков, включая выбранную вначале группу
        return $this->child_groups_arr;
    }

    # собираем потомков групп для подсчета в них товаров аналогично функции выше
    public function child_gr_num_prod($group_id_prod, $child_gr_arr) {
        if (!in_array($group_id_prod, $this->child_groups_for_nums)) {
            $this->child_groups_for_nums[] = $group_id_prod;
        }
        foreach ($this->groups as $group) {
            if ($group[id_parent] == $group_id_prod) {
                if (!in_array($group[id], $this->child_groups_for_nums)) {
                    $this->child_groups_for_nums[] = $group[id];
                }
                $this->child_gr_num_prod($group[id], $this->child_groups_for_nums);
            }
        }
        return $this->child_groups_for_nums;
    }

    #делаем вывод продуктов из выбранной группы и групп потомков
    public function view_products($products, $child_result) {
        foreach ($this->products as $product) {
            #если группа с товарами есть в массиве потомков, выводим товары этой группы
            if (in_array($product[id_group], $this->child_result)) {
                echo $product[name] . "<br>";
            }
        }
    }

}

$c_tree = new TreeMain();
#прижимаем блок в лево, что б следудющий блок встал справа от него
echo "<div style='float:left; height:100%'>";
#выводим дерево групп
$c_tree->tree($id_parent=0, $level=0);
echo "</div>";
#выравниваем блок с отступом в 10px слева
echo "<div style='float:left; height:100%; margin-left:10px;'>";
#выводим список товаров
$c_tree->view_products($products, $child_result);
echo "</div>";
?>