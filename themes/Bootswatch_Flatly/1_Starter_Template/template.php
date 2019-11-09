<?php

global $page, $config;
$lang = isset($page->lang) ? $page->lang : $config['language'];

?><!DOCTYPE html>
<html lang="<?php echo $lang; ?>" class="bootstrap-3">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">

		<?php
		common::LoadComponents( 'bootstrap3-js' );
		gpOutput::GetHead();
		?>

		<!--[if lt IE 9]><?php
		// HTML5 shim, for IE6-8 support of HTML5 elements
		gpOutput::GetComponents( 'html5shiv' );
		gpOutput::GetComponents( 'respondjs' );
		?><![endif]-->
	</head>


	<body>
		<div class="navbar navbar-default navbar-fixed-top gp-fixed-adjust">
			<div class="container">
				<div class="navbar-header">
					<button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
					</button>
					<?php
					global $config;
					echo common::Link('',$config['title'],'','class="navbar-brand"');
					?>
				</div>
				<div class="collapse navbar-collapse">
					<?php
					$GP_ARRANGE = false;
					$GP_MENU_CLASSES = array(
							'menu_top'			=> 'nav navbar-nav',
							'selected'			=> '',
							'selected_li'		=> 'active',
							'childselected'		=> '',
							'childselected_li'	=> '',
							'li_'				=> '',
							'li_title'			=> '',
							'haschildren'		=> 'dropdown-toggle',
							'haschildren_li'	=> 'dropdown',
							'child_ul'			=> 'dropdown-menu',
							);

					gpOutput::Get('TopTwoMenu'); //top two levels
					?>
				</div><!--/.nav-collapse -->
			</div>
		</div>


		<div class="container">
		<?php
		$page->GetContent();
		?>
		<hr/>
		<footer><p>
		<?php
		gpOutput::GetAdminLink();
		?>
		</p></footer>

		</div> <!-- /container -->
	</body>
</html>

