<!DOCTYPE html>
<html lang="en">
<head>
  <meta http-equiv="X-UA-Compatible" content="chrome=1">
<?php include_http_metas() ?>
<?php include_metas() ?>
<?php include_title() ?>
<link rel="shortcut icon" href="/favicon.ico" />
<?php include_stylesheets() ?>
<?php include_javascripts() ?>
<!--[if IE]>
  <!-- Ask for Google Chrome Frame to be installed if needed -->
  <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/chrome-frame/1/CFInstall.min.js"></script>
  <script type="text/javascript" src="/js/chromeframe.js"></script>
<![endif]-->
</head>
<body>
<?php include_component('cpAdminMenu', 'menu') ?>
<?php echo $sf_content ?>
</body>
</html>
