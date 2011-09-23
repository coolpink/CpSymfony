
<h1><?php echo $page->getNavTitle(); ?></h1>


<h2>Children:</h2>
<ul>
<?php foreach ($page->getChildren() as $child){ ?>
  <li><?php echo $child->getLink(); ?></li>
<?php }?>
</ul>

<h2>Parent:</h2>
<?php if ($page->getParent() != null){ ?>
<p><?php echo $page->getParent()->getLink(); ?></p>
<?php } ?>

<?php
foreach ($page->getData() as $key => $data){
	echo "<h3>{$key}</h3>";
	echo "<p>{$data}</p>";
}
?>