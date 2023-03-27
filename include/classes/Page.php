<?php
class Page {

    public $metas= [];
    public $scripts = [];
    public $scripts_top = [];
    public $stylesheets = [];

    public $page = [];


    public function __construct(){

    }

    public function add_stylesheet($src, $tags = ""){
        array_push($this->stylesheets, "<link href='$src' rel='stylesheet' $tags>");
    }
    
    function add_script($src, $tags = "", $top = false){
        if($top){
          array_push($this->scripts_top, "<script src='$src' $tags></script>");
        } else {
          array_push($this->scripts, "<script src='$src' $tags></script>");
        }
    }

    public function add_meta($string){
        array_push($this->metas, "<meta $string>");
    }

    public function set($key, $value){
        $this->page[$key] = $value;
    }

    public function add($key, $value){
      $this->set($key, $this->get($key).$value );
    }

    public function get($key){
        return empty($this->page[$key]) ? "" : $this->page[$key];
    }


    public function output(){

        $this->add_meta("charset='utf-8'");
        $this->add_meta("name='viewport' content='width=device-width, initial-scale=1.0'");
    
        $this->add_meta('name="description" content="'.$this->get("description").'"');
    
        $this->add_meta('property="og:description" content="'.$this->get("description").'"');
        $this->add_meta("property='og:title' content='".$this->get("title_prefix") . $this->get("title") . $this->get("title_suffix")."'");
        $this->add_meta('property="og:image" content="'.$this->get("imageurl").'"');
        $this->add_meta('property="og:type" content="article"');
    
        $this->add_meta('name="twitter:image" content="'.$this->get("imageurl").'"');
        $this->add_meta('name="twitter:card" content="summary_large_image"');
    
        //$this->add_meta('http-equiv="cache-control" content="public"');
        ?>
          <!DOCTYPE html>
          <html lang="en">
            <head>
              <?php echo implode("\n", $this->metas); ?>
    
              <title><?php echo $this->get("title_prefix") . $this->get("title") . $this->get("title_suffix") ?></title>
    
              <?php echo implode("\n", $this->stylesheets); ?>
            </head>
            <body>
              <?php echo implode("\n", $this->scripts_top); ?>

              <header>
                <h1><?php echo $this->get("title"); ?></h1>
                <?php echo $this->get("header"); ?>
              </header>

              <article>
                <?php echo $this->get("content"); ?>
              </article>

              <footer>
                <?php echo $this->get("footer"); ?>
              </footer>
    
              <?php echo implode("\n", $this->scripts); ?>
    
              <!--Backend execution time: <?php echo microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"] ?> seconds-->
            </body>
          </html>
          <?php
      }

}
?>