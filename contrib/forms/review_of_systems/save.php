<?php

require_once(__DIR__ . "/../../globals.php");
require_once("$srcdir/api.inc.php");

require("C_FormReviewOfSystems.class.php");

$c = new C_FormReviewOfSystems();
echo $c->default_action_process();
@formJump();
