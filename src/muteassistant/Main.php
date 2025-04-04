<?php
declare(strict_types=1);

namespace muteassistant;

use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as TF;
use muteassistant\form\Form;
use SQLite3;

class Main extends PluginBase implements Listener {

    public const string INFO = TF::BOLD . TF::YELLOW . "INFO " . TF::RESET . TF::YELLOW;
    public const string ERROR = TF::BOLD . TF::RED . "ERROR " . TF::RESET . TF::RED;
    public SQLite3 $db;
    public array $target = [];
    public Form $form;

    public function onLoad(): void {
        $this->getLogger()->info(self::INFO . "Plugin is loading...");
        @mkdir($this->getDataFolder());
        $this->db = new SQLite3($this->getDataFolder() . "MuteListData.db");
        $this->db->exec("CREATE TABLE IF NOT EXISTS mutelist(player TEXT PRIMARY KEY, time INT, reason TEXT, staff TEXT);");
    }

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->form = new Form($this);
    }

    public function onDisable(): void {
        $this->getLogger()->info(self::INFO . "Plugin disabled.");
    }

    public function onPlayerChat(PlayerChatEvent $ev): void {
        $player = $ev->getPlayer();
        $pn = $player->getName();
        $db = $this->db->query("SELECT * FROM mutelist WHERE player = '$pn';");
        $data = $db->fetchArray(SQLITE3_ASSOC);

        if(empty($data)) return;

        if($data["time"] == -1){
            $ev->cancel();
            $player->sendMessage(self::INFO . "You have been muted permanently. If we made a mistake contact us to review.");
            return;
        }

        if($data["time"] > time()){
            $ev->cancel();
            $this->sendTimeLeftMessage($player, $data["time"]);
        } else {
            $this->db->query("DELETE FROM mutelist WHERE player = '$pn';");
        }
    }

    private function sendTimeLeftMessage(Player $player, int $muteTime): void {
        $rt = $muteTime - time();
        $rtd = floor($rt / 86400);
        $rths = $rt % 86400;
        $rth = floor($rths / 3600);
        $rtms = $rths % 3600;
        $rtm = floor($rtms / 60);
        $rtss = $rtms % 60;
        $rts = ceil($rtss);

        $player->sendMessage(self::INFO . "You have been muted for " . TF::LIGHT_PURPLE .
            "$rtd days, $rth hours, $rtm minutes, $rts seconds" . TF::YELLOW . ". Please avoid chatting!");
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if(!$sender instanceof Player){
            $sender->sendMessage(self::ERROR . "You can use this command only in-game");
            return true;
        }

        if(!$sender->hasPermission("muteassistant.command.mute")){
            $sender->sendMessage(self::ERROR . "You don't have permission to use this command");
            return true;
        }

        $this->form->openMainForm($sender);
        return true;
    }

    public function mute(Player $staff, Player $target, string $reason, $day = 0, $hour = 0, $minute = 0): bool {
        if($day === "N/A"){
            $this->executePermanentMute($staff, $target, $reason);
            return true;
        }

        $this->executeTemporaryMute($staff, $target, $reason, $day, $hour, $minute);
        return true;
    }

    private function executePermanentMute(Player $staff, Player $target, string $reason): void {
        $db = $this->db->prepare("INSERT OR REPLACE INTO mutelist (player, time, reason, staff) VALUES (:player, :time, :reason, :staff);");
        $db->bindValue(":player", $target->getName());
        $db->bindValue(":time", -1);
        $db->bindValue(":reason", $reason);
        $db->bindValue(":staff", $staff->getName());
        $db->execute();

        $staff->sendMessage(self::INFO . "Player " . TF::LIGHT_PURPLE . $target->getName() . TF::YELLOW . " muted for " . TF::RED . "EVER");
        $target->sendMessage(self::INFO . "You have been muted from chat for " . TF::RED . "EVER" . TF::YELLOW . "! It was a mistake? Contact us to review.");
    }

    private function executeTemporaryMute(Player $staff, Player $target, string $reason, int $day, int $hour, int $minute): void {
        $mt = time() + ($day * 86400) + ($hour * 3600) + ($minute * 60);

        $db = $this->db->prepare("INSERT OR REPLACE INTO mutelist (player, time, reason, staff) VALUES (:player, :time, :reason, :staff);");
        $db->bindValue(":player", $target->getName());
        $db->bindValue(":time", $mt);
        $db->bindValue(":reason", $reason);
        $db->bindValue(":staff", $staff->getName());
        $db->execute();

        $staff->sendMessage(self::INFO . "Player " . TF::LIGHT_PURPLE . $target->getName() . TF::YELLOW .
            " muted for " . TF::LIGHT_PURPLE . "$day days, $hour hours, $minute minutes");
        $target->sendMessage(self::INFO . "You have been muted from chat for " . TF::LIGHT_PURPLE .
            "$day days, $hour hours, $minute minutes" . TF::YELLOW . ". Please avoid chatting!");
    }
}