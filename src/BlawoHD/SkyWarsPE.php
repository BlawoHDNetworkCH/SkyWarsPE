<?php

namespace BlawoHD;

use pocketmine\level\Position;
use pocketmine\inventory\ChestInventory;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\scheduler\PluginTask;
//use pocketmine\level\sound\PopSound;
//use pocketmine\level\sound\EndermanTeleportSound;
//use pocketmine\level\sound\FizzSound;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\utils\Config;
use pocketmine\block\Block;
use pocketmine\level\Level;
use pocketmine\utils\TextFormat;
use pocketmine\item\item;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\Byte;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Double;
use pocketmine\nbt\tag\Enum;
use pocketmine\tile\Tile;
use pocketmine\tile\Chest;
use pocketmine\tile\Sign;
use pocketmine\entity\Effect;

class SkyWarsPE extends PluginBase implements Listener {

    public $prefix = TextFormat::GRAY . "[" . TextFormat::RED . "Sky" . TextFormat::WHITE . "wars" . TextFormat::GRAY . "] " . TextFormat::WHITE;
    public $arenas = array();
    public $kit = array();
    public $signregister = false;
    public $temparena = "";
    public $signregisterstats = false;

    public function onEnable() {

        @mkdir($this->getDataFolder());

        //$signs = new Config($this->getDataFolder() . "signs.yml", Config::YAML);

        if (!file_exists($this->getDataFolder() . "kits.yml")) {
            $kitcfg = new Config($this->getDataFolder() . "kits.yml", Config::YAML);
            $kitcfg->setNested("Kits", array("Maurer", "Jumper"));

            $kitcfg->setNested("Maurer.VIP", false);

            $kitcfg->setNested("Maurer.Effekt1", null);
            $kitcfg->setNested("Maurer.Effekt2", null);
            $kitcfg->setNested("Maurer.Effekt3", null);
            $kitcfg->setNested("Maurer.Effekt4", null);
            $kitcfg->setNested("Maurer.Effekt5", null);

            $kitcfg->setNested("Maurer.Helm", Item::GOLD_HELMET);
            $kitcfg->setNested("Maurer.Brust", 0);
            $kitcfg->setNested("Maurer.Hose", 0);
            $kitcfg->setNested("Maurer.Schuhe", 0);

            $kitcfg->setNested("Maurer.Items", array(array(Item::BRICKS_BLOCK, 0, 64), array(Item::BRICKS_BLOCK, 0, 64), array(Item::DIAMOND_PICKAXE, 0, 1)));



            $kitcfg->setNested("Jumper.VIP", true);

            $kitcfg->setNested("Jumper.Effekt1", array(8, 23333, 4, false));
            $kitcfg->setNested("Jumper.Effekt2", null);
            $kitcfg->setNested("Jumper.Effekt3", null);
            $kitcfg->setNested("Jumper.Effekt4", null);
            $kitcfg->setNested("Jumper.Effekt5", null);

            $kitcfg->setNested("Jumper.Helm", Item::GOLD_HELMET);
            $kitcfg->setNested("Jumper.Brust", Item::GOLD_CHESTPLATE);
            $kitcfg->setNested("Jumper.Hose", Item::GOLD_LEGGINGS);
            $kitcfg->setNested("Jumper.Schuhe", Item::DIAMOND_BOOTS);

            $kitcfg->setNested("Jumper.Items", array(array(Item::BRICKS_BLOCK, 0, 64), array(Item::BRICKS_BLOCK, 0, 64)));

            $kitcfg->save();
        }




        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        @mkdir($this->getDataFolder());
        @mkdir($this->getDataFolder() . "Players");
        @mkdir($this->getDataFolder() . "maps");
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);

        if ($config->get("arenas") == null) {
            $config->set("arenas", array("SW1"));
            $config->save();
        }
        $items = array(
            array(261, 0, 1),
            array(262, 0, 5),
            array(298, 0, 1),
            array(299, 0, 1),
            array(300, 0, 1),
            array(301, 0, 1)
        );
        if ($config->get("chestitems") == null) {
            $config->set("chestitems", $items);
            $config->save();
        }
        $this->arenas = $config->get("arenas");
        foreach ($this->arenas as $arena) {
            $this->resetArena($arena);
            if (file_exists($this->getServer()->getDataPath() . "worlds/" . $arena)) {
                $this->getLogger()->Info("Arena -> " . $arena . " <- wurde geladen");
                $this->getServer()->loadLevel($arena);
            }
        }

        $this->getServer()->getScheduler()->scheduleRepeatingTask(new SWGameSender($this), 20);
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new SWRefreshSigns($this), 20);
        $this->getLogger()->info(TextFormat::GRAY . "===========================");
        $this->getLogger()->info(TextFormat::GREEN . "© BlawoHD / 2016 - 2017");
        $this->getLogger()->info(TextFormat::GREEN . "SkyWars wurde Aktiviert!");
        $this->getLogger()->info(TextFormat::GRAY . "===========================");
    }

    public function resetArena($arena) {
        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $level = $this->getServer()->getLevelByName($arena);
        if ($level instanceof Level) {
            $this->getServer()->unloadLevel($level);
            $this->getServer()->loadLevel($arena);
        }
        $config->set($arena . "LobbyTimer", 61);
        $config->set($arena . "EndTimer", 16);
        $config->set($arena . "GameTimer", 30 * 60 + 1);
        $config->set($arena . "Status", "Lobby");
        $config->save();
    }

    public function onBreak(BlockBreakEvent $event) {
        $player = $event->getPlayer();

        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $welt = $player->getLevel()->getFolderName();

        if (in_array($welt, $this->arenas)) {
            $status = $config->get($welt . "Status");

            if ($status == "Lobby") {
                $event->setCancelled(TRUE);
                $player->sendMessage($this->prefix . "Du kannst erst Blöcke abbauen, wenn das Spiel begonnen hat!");
            }
        }
    }

    public function onPlace(BlockPlaceEvent $event) {
        $player = $event->getPlayer();

        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $welt = $player->getLevel()->getFolderName();

        if (in_array($welt, $this->arenas)) {
            $status = $config->get($welt . "Status");

            if ($status == "Lobby") {
                $event->setCancelled(TRUE);
                $player->sendMessage($this->prefix . "Du kannst erst Blöcke setzten, wenn das Spiel begonnen hat!");
            }
        }
    }

    public function onHit(EntityDamageEvent $event) {

        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        if ($event->getEntity() instanceof Player) {
            $entity = $event->getEntity();

            if (in_array($event->getEntity()->getLevel()->getFolderName(), $this->arenas)) {
                if ($config->get($event->getEntity()->getLevel()->getFolderName() . "Status") == "Lobby") {
                    $event->setCancelled();
                }
            }

            if ($event instanceof EntityDamageByEntityEvent) {

                if ($event->getEntity() instanceof Player && $event->getDamager() instanceof Player) {

                    $victim = $event->getEntity();
                    $status = "-";
                    $damager = $event->getDamager();

                    if (in_array($event->getEntity()->getLevel()->getFolderName(), $this->arenas)) {
                        if ($config->get($victim->getLevel()->getFolderName() . "Status") == "Lobby") {
                            $event->setCancelled();
                            $damager->sendMessage($this->prefix . "PvP ist nur während der Spielzeit möglich!");
                        }
                    }
                }
            }
        }
    }

    public function onQuit(PlayerQuitEvent $event) {

        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $playerE = $event->getPlayer();
        $nameE = $playerE->getName();
        $playerE->removeAllEffects();
        $welt = $playerE->getLevel()->getFolderName();

        $status = "-";
        $maxplayers = "-";


        if (in_array($welt, $this->arenas)) {
            $status = $config->get($welt . "Status");
            $maxplayers = $config->get($welt . "Spieleranzahl");
        }

        $event->setQuitMessage("");

        if (in_array($playerE->getLevel()->getFolderName(), $this->arenas)) {

            foreach ($playerE->getLevel()->getPlayers() as $p) {
                $player = $p;

                if ($status != "Lobby") {
                    $aliveplayers = count($this->getServer()->getLevelByName($welt)->getPlayers());
                    $aliveplayers--;
                    $maxplayers = $config->get($welt . "Spieleranzahl");
                    $p->sendMessage($this->prefix . $nameE . " ist geleavt. Noch " . TextFormat::YELLOW . "$aliveplayers" . "/" . $maxplayers . TextFormat::WHITE . " Spieler!");
                }
            }
        }
    }

    public function onDeath(PlayerDeathEvent $event) {

        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $playerE = $event->getEntity();
        $playerE->removeAllEffects();
        $nameE = $playerE->getName();
        $welt = $playerE->getLevel()->getFolderName();

        $status = "-";
        $maxplayers = "-";

        if (in_array($welt, $this->arenas)) {
            $status = $config->get($welt . "Status");
            {
                $maxplayers = $config->get($welt . "Spieleranzahl");
            }

            if (in_array($playerE->getLevel()->getFolderName(), $this->arenas)) {

                foreach ($playerE->getLevel()->getPlayers() as $p) {
                    $player = $p;

                    if ($status == "Lobby") {
                        
                    } else {
                        $aliveplayers = count($this->getServer()->getLevelByName($welt)->getPlayers());
                        $aliveplayers--;
                        $p->sendMessage($this->prefix . $nameE . " ist Gestorben. Noch " . TextFormat::YELLOW . "$aliveplayers" . "/" . $maxplayers . TextFormat::WHITE . " Spieler!");
                    }
                }
            }
        }
    }

    public function copymap($src, $dst) {
        $dir = opendir($src);
        @mkdir($dst);
        while (false !== ( $file = readdir($dir))) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if (is_dir($src . '/' . $file)) {
                    $this->copymap($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    public function onInteract(PlayerInteractEvent $event) {

        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $itemID = $event->getPlayer()->getInventory()->getItemInHand()->getID();
        $block = $event->getBlock();
        $chest = $event->getPlayer()->getLevel()->getTile($event->getBlock());
        $blockID = $block->getID();
        $player = $event->getPlayer();
        $arena = $player->getLevel()->getFolderName();
        $tile = $player->getLevel()->getTile($block);

        if ($tile instanceof Sign) {

            if ($this->signregister === true && $this->signregisterWHO == $player->getName()) {
                $tile->setText($this->prefix, $this->temparena, TextFormat::GREEN . "Loading..", "");
                $this->signregister = false;
            }

            if ($this->signregisterstats === true && $this->signregisterstatsWHO == $player->getName()) {
                $tile->setText("§8[§6Stats§8]", "§8==]§b" . $this->statsID . "§8[==", "-", "§aLoading...");
                $this->signregisterstats = false;
            }

            $text = $tile->getText();
            if ($text[0] == $this->prefix) {
                if ($text[2] == TextFormat::GREEN . "Beitreten") {
                    $spieleranzahl = count($this->getServer()->getLevelByName($text[1])->getPlayers());
                    $maxplayers = $config->get($text[1] . "Spieleranzahl");
                    if ($spieleranzahl < $maxplayers) {
                        $level = $this->getServer()->getLevelByName($text[1]);
                        $spawn = $level->getSafeSpawn();
                        $level->loadChunk($spawn->getX(), $spawn->getZ());
                        $player->teleport($spawn, 0, 0);
                        $player->getInventory()->clearAll();
                        $player->removeAllEffects();
                        $player->setFood(20);
                        $player->setHealth(20);
                    } else {
                        $player->sendMessage($this->prefix . "Arena " . $text[1] . " ist voll!");
                    }
                } else {
                    $player->sendMessage($this->prefix . "Du kannst diesem Match nicht beitreten!");
                }
            }
        }
    }

    public function giveKit(Player $player) {
        $name = $player->getName();

        if (!isset($this->kit[$name])) {
            $player->sendMessage($this->prefix . "Du hast kein Kit ausgewählt!");
            $this->kit[$name] = "-";
        } else {
            $kitname = $this->kit[$name];


            $kitcfg = new Config($this->getDataFolder() . "kits.yml", Config::YAML);
            $inv = $player->getInventory();
            $inv->clearAll();
            $player->removeAllEffects();

            $helm = $kitcfg->getNested($kitname . ".Helm");
            $brust = $kitcfg->getNested($kitname . ".Brust");
            $hose = $kitcfg->getNested($kitname . ".Hose");
            $schuhe = $kitcfg->getNested($kitname . ".Schuhe");

            $items = $kitcfg->getNested($kitname . ".Items");

            $effect1 = $kitcfg->getNested($kitname . ".Effekt1");
            $effect2 = $kitcfg->getNested($kitname . ".Effekt2");
            $effect3 = $kitcfg->getNested($kitname . ".Effekt3");
            $effect4 = $kitcfg->getNested($kitname . ".Effekt4");
            $effect5 = $kitcfg->getNested($kitname . ".Effekt5");

            $inv->setHelmet(Item::get($helm, 0, 1));
            $inv->setChestplate(Item::get($brust, 0, 1));
            $inv->setLeggings(Item::get($hose, 0, 1));
            $inv->setBoots(Item::get($schuhe, 0, 1));

            if ($effect1 != null) {
                $effect = Effect::getEffect($effect1[0]);
                $effect->setDuration((int) $effect1[1]);
                $effect->setAmplifier((int) $effect1[2]);
                $effect->setVisible($effect1[3]);

                $player->addEffect($effect);
            }
            if ($effect2 != null) {
                $effect = Effect::getEffect($effect2[1]);
                $effect->setDuration((int) $effect2[2]);
                $effect->setAmplifier((int) $effect2[3]);
                $effect->setVisible($effect2[4]);

                $player->addEffect($effect);
            }
            if ($effect3 != null) {
                $effect = Effect::getEffect($effect3[1]);
                $effect->setDuration((int) $effect3[2]);
                $effect->setAmplifier((int) $effect3[3]);
                $effect->setVisible($effect3[4]);

                $player->addEffect($effect);
            }
            if ($effect4 != null) {
                $effect = Effect::getEffect($effect4[1]);
                $effect->setDuration((int) $effect4[2]);
                $effect->setAmplifier((int) $effect4[3]);
                $effect->setVisible($effect4[4]);

                $player->addEffect($effect);
            }
            if ($effect5 != null) {
                $effect = Effect::getEffect($effect5[1]);
                $effect->setDuration((int) $effect5[2]);
                $effect->setAmplifier((int) $effect5[3]);
                $effect->setVisible($effect5[4]);

                $player->addEffect($effect);
            }


            foreach ($items as $i) {
                $player->getInventory()->addItem(Item::get($i[0], $i[1], $i[2]));
            }

            $player->sendMessage($this->prefix . "Du hast das Kit " . TextFormat::GOLD . $kitname . TextFormat::WHITE . " ausgewählt!");
        }
    }

    public function fillChests(Level $level) {
        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $tiles = $level->getTiles();
        foreach ($tiles as $t) {
            if ($t instanceof Chest) {
                $chest = $t;
                $chest->getInventory()->clearAll();
                if ($chest->getInventory() instanceof ChestInventory) {
                    for ($i = 0; $i <= 26; $i++) {
                        $rand = rand(1, 3);
                        if ($rand == 1) {
                            $k = array_rand($config->get("chestitems"));
                            $v = $config->get("chestitems")[$k];
                            $chest->getInventory()->setItem($i, Item::get($v[0], $v[1], $v[2]));
                        }
                    }
                }
            }
        }
    }

    public function onCommand(CommandSender $sender, Command $cmd, $label, array $args) {
        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $kitcfg = new Config($this->getDataFolder() . "kits.yml", Config::YAML);
        $allKits = $kitcfg->get("Kits");

        $arena = $sender->getLevel()->getFolderName();
        if (in_array($arena, $this->arenas)) {
            $status = $config->get($arena . "Status");
        } else {
            $status = "NO-ARENA";
        }

        if ($cmd->getName() == "kits") {
            $sender->sendMessage($this->prefix . "Kits: ");

            foreach ($allKits as $k) {
                $vip = $kitcfg->getNested($k . ".VIP");
                if ($vip == false) {
                    $sender->sendMessage("§7- §a" . $k);
                } else {
                    $sender->sendMessage("§7- §6" . $k);
                }
            }
        }
        if ($cmd->getName() == "kit") {
            if (!empty($args[0])) {

                if ($status != "NO-ARENA") {
                    if ($status == "Lobby") {

                        $kitname = $args[0];

                        $vip = $kitcfg->getNested($kitname . ".VIP");
                        $allKits = $kitcfg->get("Kits");


                        if (in_array($kitname, $allKits)) {
                            if ($vip == true) {
                                if ($sender->hasPermission("sw.vip") || $sender->isOP()) {

                                    $this->kit[$sender->getName()] = $kitname;
                                    $sender->sendMessage($this->prefix . "Du hast das Kit " . TextFormat::GOLD . $kitname . TextFormat::WHITE . " ausgewählt!");
                                }
                            } else {
                                $this->kit[$sender->getName()] = $kitname;
                                $sender->sendMessage($this->prefix . "Du hast das Kit " . TextFormat::GOLD . $kitname . TextFormat::WHITE . " ausgewählt!");
                            }
                        } else {
                            $sender->sendMessage($this->prefix . TextFormat::RED . "Dieses Kit existiert nicht!");
                        }
                    } else {
                        $sender->sendMessage($this->prefix . TextFormat::RED . "Die Runde hat schon begonnen!");
                    }
                }
            } else {
                $sender->sendMessage($this->prefix . TextFormat::GRAY . "/kit <Kitname>");
                $sender->sendMessage($this->prefix . TextFormat::GRAY . "/kits -> Zeigt alle Kits");
            }
        }

        if ($cmd->getName() == "Skywars") {
            if (!empty($args[0])) {
                if ($args[0] == "addarena" && $sender->isOP()) {
                    if (!empty($args[1]) && !empty($args[2])) {
                        if (file_exists($this->getServer()->getDataPath() . "worlds/" . $args[1])) {
                            $arena = $args[1];
                            $this->arenas[] = $arena;
                            $config->set("arenas", $this->arenas);
                            $config->set($arena . "Spieleranzahl", (int) $args[2]);
                            $config->save();
                            $this->copymap($this->getServer()->getDataPath() . "worlds/" . $arena, $this->getDataFolder() . "maps/" . $arena);
                            $this->resetArena($arena);
                            $sender->sendMessage($this->prefix . "Du hast eine Neue Arena hinzugefügt! -> /Skywars help");
                        }
                    }
                } elseif ($args[0] == "refill" && $sender->isOP()) {
                    $this->fillChests($this->getServer()->getLevelByName($sender->getLevel()->getFolderName()));
                    $sender->sendMessage($this->prefix . "Alle Kisten auf der Map " . $sender->getLevel()->getFolderName() . " wurden befüllt");
                } elseif ($args[0] == "stats" && $sender->isOP()) {
                    if (!empty($args[1])) {
                        if ($args[1] == 1 || $args[1] == 2 || $args[1] == 3) {
                            $this->signregisterstats = true;
                            $this->signregisterstatsWHO = $sender->getName();
                            $this->statsID = $args[1];
                            $sender->sendMessage($this->prefix . "Tippe nun ein schild an um die Stats drauf anzuzeigen");
                        } else {
                            $sender->sendMessage($this->prefix . "-> /sw stats <1 | 2 | 3>");
                        }
                    } else {
                        $sender->sendMessage($this->prefix . "-> /sw stats <1 | 2 | 3>");
                    }



                    /*
                      $this->signregisterstats = true;
                      $this->signregisterstatsWHO = $sender->getName();
                      $this->statsID = $args[1];
                      "§8==]§b1§8[==","§8[§6Stats§8]"
                     */
                } elseif ($args[0] == "regsign" && $sender->isOP()) {
                    if (!empty($args[1])) {

                        $this->signregister = true;
                        $this->signregisterWHO = $sender->getName();
                        $this->temparena = $args[1];

                        $sender->sendMessage($this->prefix . "Tippe nun ein schild an um es zu registrieren");
                    }
                } elseif ($args[0] == "help" && $sender->isOP()) {

                    $sender->sendMessage(TextFormat::GRAY . "====================");
                    $sender->sendMessage(TextFormat::GRAY . "-> /sw setspawn <SpawnID>");
                    $sender->sendMessage(TextFormat::GRAY . "-> /sw addarena <Weltname> <Spawnpoints>");
                    $sender->sendMessage(TextFormat::GRAY . "-> /sw regsign <Arena>");
                    $sender->sendMessage(TextFormat::GRAY . "-> /sw stats <1 | 2 | 3>");
                    $sender->sendMessage(TextFormat::GRAY . "-> /sw refill");
                    $sender->sendMessage(TextFormat::GRAY . "====================");
                } elseif ($args[0] == "setspawn" && $sender->isOP()) {
                    if (!empty($args[1])) {
                        $arena = $sender->getLevel()->getFolderName();
                        $x = $sender->getX();
                        $y = $sender->getY();
                        $z = $sender->getZ();
                        $coords = array($x, $y, $z);

                        $config->set($arena . "Spawn" . $args[1], $coords);
                        $config->save();
                        $sender->sendMessage($this->prefix . "Du hast Spawn " . $args[1] . " der Arena gesetzt!");
                    }
                } else {
                    $sender->sendMessage(TextFormat::GRAY . "====================");
                    $sender->sendMessage(TextFormat::GRAY . "-> /sw setspawn <SpawnID>");
                    $sender->sendMessage(TextFormat::GRAY . "-> /sw addarena <Weltname> <Spawnpoints>");
                    $sender->sendMessage(TextFormat::GRAY . "-> /sw regsign <Arena>");
                    $sender->sendMessage(TextFormat::GRAY . "-> /sw stats <1 | 2 | 3>");
                    $sender->sendMessage(TextFormat::GRAY . "-> /sw refill");
                    $sender->sendMessage(TextFormat::GRAY . "====================");
                }
            }
        }
    }
}

class SWRefreshSigns extends PluginTask {

    public $prefix = "";

    public function __construct($plugin) {
        $this->plugin = $plugin;
        $this->prefix = $this->plugin->prefix;
        parent::__construct($plugin);
    }

    public function onRun($tick) {
        $allplayers = $this->plugin->getServer()->getOnlinePlayers();
        $level = $this->plugin->getServer()->getDefaultLevel();
        $tiles = $level->getTiles();
        foreach ($tiles as $t) {
            if ($t instanceof Sign) {
                $text = $t->getText();
                if ($text[0] == $this->prefix) {
                    $aop = count($this->plugin->getServer()->getLevelByName($text[1])->getPlayers());
                    $ingame = TextFormat::GREEN . "Beitreten";
                    $config = new Config($this->plugin->getDataFolder() . "config.yml", Config::YAML);
                    $count = $config->get($text[1] . "Spieleranzahl");
                    if ($config->get($text[1] . "Status") != "Lobby") {
                        $ingame = TextFormat::RED . "Ingame";
                    }
                    if ($aop >= 24) {
                        $ingame = TextFormat::RED . "Voll";
                    }
                    if ($config->get($text[1] . "Status") == "Ende") {
                        $ingame = TextFormat::RED . "Restart";
                    }
                    $t->setText($this->prefix, $text[1], $ingame, TextFormat::YELLOW . $aop . "/" . $count);
                }
                if ($text[0] == "§8[§6Stats§8]") {

                    $playername = "Error";
                    $wins = "Error";

                    $sortWins = array();

                    //"§8==]§b1§8[==","§8[§6Stats§8]"

                    $files = scandir($this->plugin->getDataFolder() . "Players/");
                    foreach ($files as $file) {
                        if ($file != "." && $file != "..") {
                            $name = substr($file, 0, -4);
                            $PlayerFile = new Config($this->plugin->getDataFolder() . "Players/" . $name . ".yml", Config::YAML);
                            $winsFile = $PlayerFile->get("Wins");
							
							if(isset($sortWins["0".$winsFile])){
								$sortWins["00".$winsFile] = array($winsFile, $name);
							} elseif(isset($sortWins[$winsFile])){
								$sortWins["0".$winsFile] = array($winsFile, $name);
							} else {
								$sortWins[$winsFile] = array($winsFile, $name);
							}
                        }
                    }

                    sort($sortWins);
					
					var_dump($sortWins);
					
					if(count($sortWins) >= 1){
						$platzEinsWins = $sortWins[count($sortWins)-1][0];
						$platzEins = $sortWins[count($sortWins)-1][1];
					} else {
						$platzEins = "-";
						$platzEinsWins = 0;
					}
					
					if(count($sortWins) >= 2){
						$platzZweiWins = $sortWins[count($sortWins)-2][0];
						$platzZwei = $sortWins[count($sortWins)-2][1];
					} else {
						$platzZwei = "-";
						$platzZweiWins = 0;
					}
					
					if(count($sortWins) >= 3){
						$platzDreiWins = $sortWins[count($sortWins)-3][0];
						$platzDrei = $sortWins[count($sortWins)-3][1];
					} else {
						$platzDrei = "-";
						$platzDreiWins = 0;
					}
                    if ($text[1] == "§8==]§b1§8[==") {

                        $playername = $platzEins;
                        $wins = $platzEinsWins;
                    }
                    if ($text[1] == "§8==]§b2§8[==") {

                        $playername = $platzZwei;
                        $wins = $platzZweiWins;
                    }
                    if ($text[1] == "§8==]§b3§8[==") {

                        $playername = $platzDrei;
                        $wins = $platzDreiWins;
                    }

                    $t->setText("§8[§6Stats§8]", $text[1], $playername, "§6Wins§7: §c" . $wins);
                }
            }
        }
    }

}

class SWGameSender extends PluginTask {

    public $prefix = "";

    public function __construct($plugin) {
        $this->plugin = $plugin;
        $this->prefix = $this->plugin->prefix;
        parent::__construct($plugin);
    }

    public function onRun($tick) {
        $config = new Config($this->plugin->getDataFolder() . "config.yml", Config::YAML);
        $arenas = $config->get("arenas");
        if (count($arenas) != 0) {
            foreach ($arenas as $arena) {
                $status = $config->get($arena . "Status");
                $lobbytimer = $config->get($arena . "LobbyTimer");
                $endtimer = $config->get($arena . "EndTimer");
                $gametimer = $config->get($arena . "GameTimer");
                $levelArena = $this->plugin->getServer()->getLevelByName($arena);
                if ($levelArena instanceof Level) {
                    $players = $levelArena->getPlayers();


                    if ($status == "Lobby") {

                        if (count($players) < 2) {
                            $config->set($arena . "LobbyTimer", 61);
                            $config->set($arena . "EndTimer", 16);
                            $config->set($arena . "Status", "Lobby");
                            $config->save();

                            foreach ($players as $p) {
                                $p->sendTip(TextFormat::RED . "Warte auf weitere Teilnehmer");
                            }

                            if ((Time() % 20) == 0) {
                                foreach ($players as $p) {
                                    $p->sendMessage(TextFormat::RED . "1 Weiterer Spieler fehlt");
                                }
                            }
                        } else {

                            $lobbytimer--;
                            $config->set($arena . "LobbyTimer", $lobbytimer);
                            $config->save();

                            if ($lobbytimer == 60 ||
                                    $lobbytimer == 30 ||
                                    $lobbytimer == 20
                            ) {
                                foreach ($players as $p) {
                                    $p->sendMessage($this->prefix . "Runde startet in " . $lobbytimer . " Sekunden!");
                                }
                            }
                            if ($lobbytimer >= 1 && $lobbytimer <= 10) {
                                foreach ($players as $p) {
                                    $p->sendPopup(TextFormat::YELLOW . "Noch " . TextFormat::RED . $lobbytimer);
                                }
                            }
                            if ($lobbytimer <= 0) {

                                $countPlayers = 0;

                                foreach ($players as $p) {
                                    $countPlayers++;

                                    $spawn = $config->get($arena . "Spawn" . $countPlayers);
                                    $p->teleport(new Vector3($spawn[0], $spawn[1], $spawn[2]));
                                    $p->setFood(20);
                                    $p->setHealth(20);
                                    $p->getInventory()->clearAll();
                                    $p->removeAllEffects();

                                    $this->plugin->giveKit($p);
                                    $this->plugin->fillChests($levelArena);
                                }

                                $config->set($arena . "Status", "Ingame");
                                $config->save();
                            }
                        }
                    }
                    if ($status == "Ingame") {

                        $gametimer--;
                        $config->set($arena . "GameTimer", $gametimer);
                        $config->save();

                        $min = $gametimer / 60;

                        if ($gametimer == 30 * 60 ||
                                $gametimer == 20 * 60 ||
                                $gametimer == 10 * 60 ||
                                $gametimer == 5 * 60 ||
                                $gametimer == 4 * 60 ||
                                $gametimer == 3 * 60 ||
                                $gametimer == 120 ||
                                $gametimer == 60
                        ) {
                            foreach ($players as $p) {
                                $p->sendMessage($this->prefix . "Runde endet in " . $min . " Minuten!");
                            }
                        }
                        if ($gametimer == 30 ||
                                $gametimer == 20 ||
                                $gametimer == 10 ||
                                $gametimer == 5 ||
                                $gametimer == 4 ||
                                $gametimer == 3 ||
                                $gametimer == 2 ||
                                $gametimer == 1
                        ) {
                            foreach ($players as $p) {
                                $p->sendTip("Runde endet in " . TextFormat::GOLD . $gametimer . TextFormat::WHITE . " Sekunden!");
                            }
                        }
                        if ($gametimer == 0) {
                            $this->plugin->getServer()->broadcastMessage($this->prefix . "Die Skywars Runde in Arena " . TextFormat::GOLD . $arena . TextFormat::WHITE . " hat keiner Gewonnen - Unentschieden -");
                            $config->set($arena . "Status", "Ende");
                            $config->save();
                        }

                        if (count($players) <= 1) {
                            foreach ($players as $p) {
                                $name = $p->getName();
                                $this->plugin->getServer()->broadcastMessage($this->prefix . TextFormat::GOLD . $name . TextFormat::WHITE . " hat Die Skywars Runde in Arena " . TextFormat::GOLD . $arena . TextFormat::WHITE . " Gewonnen!");
                                $PlayerFile = new Config($this->plugin->getDataFolder() . "Players/" . $name . ".yml", Config::YAML);
                                if (empty($PlayerFile->get("Wins"))) {
                                    $PlayerFile->set("Wins", 0);
                                    $PlayerFile->save();
                                }
                                $wins = (int) $PlayerFile->get("Wins") + 1;
                                $PlayerFile->set("Wins", $wins);
                                $PlayerFile->save();
                            }
                            $config->set($arena . "Status", "Ende");
                            $config->save();
                        }
                    }
                    if ($status == "Ende") {

                        if ($endtimer >= 0) {
                            $endtimer--;
                            $config->set($arena . "EndTimer", $endtimer);
                            $config->save();

                            if ($endtimer == 15 ||
                                    $endtimer == 10 ||
                                    $endtimer == 5 ||
                                    $endtimer == 4 ||
                                    $endtimer == 3 ||
                                    $endtimer == 2 ||
                                    $endtimer == 1
                            ) {

                                foreach ($players as $p) {

                                    $p->sendMessage($this->plugin->prefix . "Arena restartet in " . $endtimer . " Sekunden !");
                                }
                            }

                            if ($endtimer == 0) {

                                $config = new Config($this->plugin->getDataFolder() . "config.yml", Config::YAML);

                                foreach ($players as $p) {
                                    $p->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn());
                                    $p->setFood(20);
                                    $p->setHealth(20);
                                    $p->getInventory()->clearAll();
                                    $p->removeAllEffects();
                                }

                                $this->plugin->getServer()->unloadLevel($levelArena);
                                $this->plugin->copymap($this->plugin->getDataFolder() . "maps/" . $arena, $this->plugin->getServer()->getDataPath() . "worlds/" . $arena);
                                $this->plugin->getServer()->loadLevel($arena);

                                $this->plugin->kit = array();

                                $config->set($arena . "LobbyTimer", 61);
                                $config->set($arena . "EndTimer", 16);
                                $config->set($arena . "Status", "Lobby");
                                $config->save();
                            }
                        }
                    }
                }
            }
        }
    }

}
