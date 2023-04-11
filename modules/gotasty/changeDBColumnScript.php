<?php
include_once('include/class.msgpack.php');
include_once('include/config.php');
include_once('include/class.admin.php');
include_once('include/class.database.php');
include_once('include/class.cash.php');
include_once('include/class.webservice.php');
include_once('include/class.user.php');
include_once('include/class.api.php');
include_once('include/class.message.php');
include_once('include/class.permission.php');
include_once('include/class.setting.php');
include_once('include/class.language.php');
include_once('include/class.provider.php');
include_once('include/class.journals.php');
include_once('include/class.country.php');
include_once('include/class.general.php');
include_once('include/class.tree.php');
include_once('include/class.activity.php');
include_once('include/class.invoice.php');
include_once('include/class.product.php');
include_once('include/class.client.php');
include_once('include/class.memo.php');
include_once('include/class.announcement.php');
include_once('include/class.document.php');
include_once('include/class.bonus.php');
include_once('include/PHPExcel.php');
include_once('include/class.log.php');
include_once('include/class.report.php');
include_once('include/class.dashboard.php');
include_once('include/class.ticket.php');

$db              = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
$setting         = new Setting($db);
$general         = new General($db, $setting);
$log             = new Log(getcwd(), "log.txt");

$msgpack         = new msgpack();

$user            = new User($db, $setting, $general);
$api             = new Api($db, $general);
$provider        = new Provider($db);
$message         = new Message($db, $general, $provider);
$webservice      = new Webservice($db, $general, $message);
$permission      = new Permission($db, $general);

$cash            = new Cash($db, $setting, $message, $provider, $log);
$language        = new Language($db, $general, $setting);
$activity        = new Activity($db, $general);

// $journals        = new Journals($db, $general);
$country         = new Country($db, $general);
$tree            = new Tree($db, $setting, $general);
$invoice         = new Invoice($db, $setting);
$product         = new Product($db, $setting, $general);
$bonus           = new Bonus($db, $general, $setting, $cash, $log);
$client          = new Client($db, $setting, $general, $country, $tree, $cash, $activity, $product, $invoice, $bonus);
$admin           = new Admin($db, $setting, $general, $cash, $invoice, $product, $country, $activity, $client, $bonus);
$memo            = new Memo($db, $general, $setting);
$announcement    = new Announcement($db, $general, $setting);
$document        = new Document($db, $general, $setting);
$report          = new Report($db, $general, $setting);

$dashboard       = new Dashboard($db, $announcement, $cash, $admin);
$ticket          = new Ticket($db, $setting);

$msgpackData = $msgpack->msgpack_unpack(file_get_contents('php://input'));
$timeStart   = time();
$tblDate     = date("Ymd");
$createTime  = date("Y-m-d H:i:s");

$command        = $msgpackData['command'];
$sessionID      = $msgpackData['sessionID'];
$userID         = $msgpackData['userID'];
$sessionTimeOut = $msgpackData['sessionTimeOut'];
$source         = $msgpackData['source'];
$site           = $msgpackData['site'];
$systemLanguage = trim($msgpackData['language'])? trim($msgpackData['language']) : "english"; // default to english


// Set current language. Call $general->getCurrentLanguage() to retrieve the current language
$general->setCurrentLanguage($language);
include_once('language/lang_all.php');
// Set the translations into general class. Call $general->getTranslations() to retrieve all the translations
$general->setTranslations($translations);

//script start

$db->where("TABLE_SCHEMA", 'mlmPlatform');
$db->where("DATA_TYPE", 'decimal');
$db->where("NUMERIC_PRECISION", 20);
$db->where("NUMERIC_SCALE", 4);
$result = $db->get("INFORMATION_SCHEMA.COLUMNS", NULL, "TABLE_NAME, COLUMN_NAME");


foreach ($result as $dbColumn){

    echo "Changing " . $dbColumn['TABLE_NAME'] . "... column " . $dbColumn['COLUMN_NAME'] . " to DECIMAL(20,8) data type\n";

    $db->rawQuery("ALTER TABLE " . $dbColumn['TABLE_NAME'] . " CHANGE " . $dbColumn['COLUMN_NAME'] . " " . $dbColumn['COLUMN_NAME'] ." DECIMAL(20,8) NOT NULL;");
}

echo "done...\n";


//script end

?>

