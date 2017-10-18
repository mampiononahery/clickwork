<?php
include('head.php');
  ?>

	<!-- Uses a header that contracts as the page scrolls down. -->
<style>
.demo-layout-transparent {
  background: url('<?php echo base_url()."assets/images"; ?>/transparent.jpg') center / cover;
}
.demo-layout-waterfall .mdl-layout__header-row .mdl-navigation__link:last-of-type  {
  padding-right: 0;
}
</style>

<div class="demo-layout-waterfall mdl-layout mdl-js-layout">
  <header class="mdl-layout__header mdl-layout__header--waterfall">
    <!-- Top row, always visible -->
    <div class="mdl-layout__header-row">
      <!-- Title -->
      <span class="mdl-layout-title">Title</span>
      <div class="mdl-layout-spacer"></div>
      <div class="mdl-textfield mdl-js-textfield mdl-textfield--expandable
                  mdl-textfield--floating-label mdl-textfield--align-right">
        <label class="mdl-button mdl-js-button mdl-button--icon"
               for="waterfall-exp">
          <i class="material-icons">search</i>
        </label>
        <div class="mdl-textfield__expandable-holder">
          <input class="mdl-textfield__input" type="text" name="sample"
                 id="waterfall-exp">
        </div>
      </div>
    </div>
    <!-- Bottom row, not visible on scroll -->
    
    	<?php include('nav.php');  ?>
 	 <main class="mdl-layout__content">
    	<div class="page-content">
    		<?php include('main.php'); ?>
    	</div>
  </main>
</div>
</body>
</html>


