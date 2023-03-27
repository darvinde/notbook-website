<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require $_SERVER["DOCUMENT_ROOT"].'/include/classes/Router.php';
require $_SERVER["DOCUMENT_ROOT"].'/include/classes/Parsedown.php';
require $_SERVER["DOCUMENT_ROOT"].'/include/classes/Page.php';
$config = parse_ini_file("config.ini", true);

$router = new Steampixel\Route();
$parsedown = new Parsedown();

$dbconf = $GLOBALS["config"]["database"];
if($dbconf["driver"] == "mysql"){
    $db = new PDO('mysql:host='.$dbconf["host"].';dbname='.$dbconf["name"], $dbconf["user"], $dbconf["password"]);
}
if($dbconf["driver"] == "sqlite"){
    $db = new PDO("sqlite:".$dbconf["file"]);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
$db->type = $dbconf["driver"];

$page = new Page();

$page->add_stylesheet("/include/css/modern-normalize.min.css");
$page->add_stylesheet("/include/css/main.css");

$page->set("footer", '© darvin.de <a href="/">Übersicht</a>');
$page->add("header", '<a class="homebutton" href="/"></a>');

if(is_logged_in()){
    $page->add("footer", " | <a href='/edit/0'>Neu</a>");
    $page->add("footer", " | <a href='/logout'>Logout</a>");
}

$router->add('/story/random', function() {
    global $db, $parsedown, $page;
    $statement = $db->prepare("SELECT * FROM stories WHERE id>1 ORDER BY RAND() LIMIT 1");
    $statement->execute();
    $story = $statement->fetch();
    header("Location: /story/".$story["id"]);
});


$router->add('/story/(.*)', function($storyid) {
    global $db, $parsedown, $page;
    $statement = $db->prepare("SELECT * FROM stories WHERE id=? AND id>1");
    $statement->execute([$storyid]);

    if($story = $statement->fetch()){
        $content = $parsedown->text($story["text_md"]);

        $edit_date = strtotime($story["edit_date"]);
        $edit_date = date('d.m.Y', $edit_date);

        if(!empty($story["source_md"]))
            $content .= "<span class='grey'>".$parsedown->text("Quelle: ".$story["source_md"])."</span>";

        $page->set("title", $story["title"]);
        $page->set("content", $content);
        $page->add("header", "<span class='date'>$edit_date</span>");

        if(is_logged_in())
            $page->add("footer", " | <a href='/edit/$storyid'>Edit</a>");

        $page->output();
        die();
    } else {
        page_404();
    }
});

$router->add('/edit/(.*)', function($storyid) {
    global $db, $parsedown, $page;
    login_access();

    $statement = $db->prepare("SELECT * FROM stories WHERE id=?");
    $statement->execute([$storyid]);

    if(!$story = $statement->fetch()){ //Use story with id 1 as default
        $statement->execute([1]);
        $story = $statement->fetch();
    }

    ob_start();
    ?>
    <form method="post">
        
        <label for="title">Titel:</label>
        <input type="text" id="title" name="title" value="<?php echo $story["title"]; ?>"><br><br>
        
        <label for="text_md">Text:</label>
        <textarea id="text_md" name="text_md"><?php echo $story["text_md"]; ?></textarea><br><br>


        <label for="source_md">
            Quelle: <span class="grey">Name [Link Text](https://example.com)</span>
        </label>
        <input type="text" id="source_md" name="source_md" value="<?php echo $story["source_md"]; ?>"><br><br>

        <input type="submit" value="Submit">
    </form>
    <?php


    $page->set("title", $story["title"]);
    $page->set("content", ob_get_clean());
    $page->output();

}, "get");

$router->add('/edit/(.*)', function($storyid) {
    global $db, $parsedown, $page;
    login_access();

    $statement = $db->prepare("REPLACE INTO stories (id, title, text_md, source_md) VALUES (?, ?, ?, ?)");
    $statement->execute([$storyid, $_POST["title"], $_POST["text_md"], $_POST["source_md"]]);

    //When inserting to id = 0, the databse inserts at a new id
    header("Location: /story/".$db->lastInsertID());

}, "post");


$router->add('/', function() {
    global $db, $parsedown, $page;
    $statement = $db->query("SELECT * FROM stories WHERE ID > 1 ORDER BY id DESC");

    $content = "";
    while($story = $statement->fetch()){
        $edit_date = strtotime($story["edit_date"]);
        $edit_date = date('d.m.Y', $edit_date);
        $content .= "<p><a href='/story/".$story["id"]."'>".$story["title"]."</a> <span class='grey'>(".$edit_date.")</span></p>";
    }

    $page->set("title", "Story.<br>Kurzgeschichten.");
    $page->set("header", '<p>Zufällige Kurzgeschichten, die zum Nachdenken anregen oder auch scheinbar sinnlos sind - und dennoch ihre eigene Faszination haben.</p>');

    $page->add("header", "<p><a class='icon' href='/story/random'><img src='/include/img/css/cube_random.png'><span>Zufällige Story</span></a></p>");


    $page->set("content", $content);
    $page->output();

});

$router->add("/login", function() {
    global $db, $parsedown, $page;
    
    if(is_logged_in()){
        $url = empty($_SERVER['HTTP_REFERER']) ? "/" : $_SERVER['HTTP_REFERER'];
        header("location: $url");
    }

    ob_start();

    if (isset($_GET["wrongpassword"])){
        echo "Falsches Passwort!";
    }
    ?>
    <form method="post">
        <label for="password">Passwort</label><br>
        <input type="password" id="password" name="password">
        <input type="submit" value="Login">
    </form>
    <?php


    $page->set("title", "Login");
    $page->set("content", ob_get_clean());
    $page->output();
}, 'get');

function login_access(){
    if(is_logged_in()) return;

    header("Location: /login?redirect=".$_SERVER['REQUEST_URI']);
}
function is_logged_in() {
    @session_start();
    return isset($_SESSION['login']) && $_SESSION['login'];
}

$router->add("/login", function() {
    global $db, $parsedown, $page;

    if($_POST["password"] == $GLOBALS["config"]["story"]["password"]) {
        @session_start();
        $_SESSION['login'] = true;
        $url = empty($_GET["redirect"]) ? "/" : $_GET["redirect"];
        header("location: $url");
    } else {
        header("Location: /login?wrongpassword");
    }

}, 'post');

$router->add("/logout", function() {
    $_SESSION = array();
    @session_start();

    setcookie("PHPSESSID", '', time() - 1, '/');
    setcookie("session", '', time() - 1, '/');
    @session_destroy();

    $url = empty($_SERVER['HTTP_REFERER']) ? "/" : $_SERVER['HTTP_REFERER'];
    header("location: $url");
});

$router->add("/setup", function() {
    global $db, $parsedown, $page;

    ob_start();

    if($db->type == "mysql"){
        $current_timestamp = "CURRENT_TIMESTAMP()";
    }
    if($db->type == "sqlite"){
        $current_timestamp = "CURRENT_TIMESTAMP";
    }

    echo "<br>Erstelle Datenbank Tabelle stories...<br>";
    $sql = "CREATE TABLE IF NOT EXISTS `stories` (
        `id` int(11) NOT NULL,
        `title` varchar(255) NOT NULL,
        `text_md` text NOT NULL,
        `source_md` varchar(255) NOT NULL DEFAULT '',
        `edit_date` datetime NOT NULL DEFAULT $current_timestamp
    );

    ALTER TABLE `stories`
        ADD PRIMARY KEY (`id`);

    ALTER TABLE `stories`
        MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;
    COMMIT;
    
    
    ";
    $db->exec($sql);

    /*TODO: Implement sqlite Create table
    Problem: sqlite has own ROWID beside id
    */
    

    echo "<br>Setup abgeschlossen.<br>";

    $page->set("title", "Setup");
    $page->set("content", ob_get_clean());
    $page->output();
    
});

$router->pathNotFound(function($path) {
    page_404();
});


$router->run();




function page_404(){
    global $db, $parsedown, $page;
    http_response_code(404);
    $page->set("title", "404");
    $page->set("content", '<a href="/">Go to homepage</a>');
    $page->output();
    die();
}

?>