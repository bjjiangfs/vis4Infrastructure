<?php
/**
 * 
 */
class Menu{
	private $html_navbar;
	function __construct($cur_tag) {
		$menuContent = array(/*'Accueil' => 'index.php',*/
							 'Architecture' => 'index.php',
							 'Flux' => 'flux.php',
							);
		
		$this->html_navbar = "";	
		foreach ($menuContent as $key => $value) {
			if($key == $cur_tag){
				$this->html_navbar .= '<li class="active"><a href="http://localhost:8888/site_schema/'.$value.'" >'.$key.'</a></li>';
			}else{
				$this->html_navbar .= '<li><a href="http://localhost:8888/site_schema/'.$value.'" >'.$key.'</a></li>';
			}
		}
		
	}
	
	public function createMenu(){
		return $this->html_navbar;
	}
}


?>