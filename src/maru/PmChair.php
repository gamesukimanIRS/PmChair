<?php

namespace maru;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\block\Stair;
use pocketmine\entity\Entity;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\entity\Item;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\network\mcpe\protocol\AddEntityPacket;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\SetEntityLinkPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\RemoveEntityPacket;
use pocketmine\Server;

class PmChair extends PluginBase implements Listener {
	private $onChair = [ ];
	private $doubleTap = [ ];
	private $messages;
	private $player;
	
	const m_version = 1;
	
	public function onEnable() {
    		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		if(!file_exists($this->getDataFolder())){ 
         		mkdir($this->getDataFolder(), 0756, true); 
       		}
       		$this->money = new Config($this->getDataFolder() . "message.yml", Config::YAML, [
			'touch-popup' => '§b座るには再タップ',
			'touch-popup' => '§b別の椅子に座るには再タップ',
			'seat-down' => '§a階段に座りました'
		]);
	}
	public function get($m) {
		return $this->Config->get($m);
	}
	public function onTouch(PlayerInteractEvent $event) {
		$player = $event->getPlayer ();
		$block = $event->getBlock ();
		if ($block instanceof Stair) {
			if (isset($this->doubleTap[$player->getName()])) {
				if(!isset($this->onChair[$player->getName()])){
					$this->SeatDown($player, $block);
					unset($this->doubleTap[$player->getName()]);
				} else {
					$this->StandUp($player);
					unset ( $this->onChair [$player->getName ()] );
					$this->SeatDown($player, $block);	
				}
			}else{
				$this->doubleTap [$player->getName ()] = "1stTapComplete";
				$player->sendPopup ($this->get("touch-popup-ver2"));
			}
		}
	}
	public function SeatDown($player, $stair){
		$sx = intval($stair->getX());
		$sy = intval($stair->getY());
		$sz = intval($stair->getZ());
		$nx = $sx + 0.5;
		$ny = $sy + 1.5;
		$nz = $sz + 0.5;
		$pk = new AddEntityPacket();
		$entityRuntimeId = $player->getId() + 10000;
		$this->onChair[$player->getName()] = $entityRuntimeId;
		$pk->entityRuntimeId = $entityRuntimeId;
		$pk->type = 84;
		$pk->position = new Vector3($nx, $ny, $nz);
		$pk->motion = new Vector3(0,0,0);
		$flags = (
			(1 << Entity::DATA_FLAG_IMMOBILE) | (1 << Entity::DATA_FLAG_INVISIBLE)
 		);
 		$pk->metadata = [
 			Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, $flags],
 		];
 		$pk->links[] = [$pk->entityRuntimeId,$this->player->getId(),Server::getInstance()->broadcastPacket(Server::getInstance()->getOnlinePlayers(), $pk)];
		$player->sendPopup($this->get("seat-down"));
	}
	public function StandUp($player){
		$removepk = new RemoveEntityPacket();
		$removepk->entityUniqueId = $this->onChair [$player->getName ()];
		Server::getInstance()->broadcastPacket ( Server::getInstance()->getOnlinePlayers (), $removepk );
	}
	public function onJump(DataPacketReceiveEvent $event) {
		$packet = $event->getPacket ();
		if ($packet instanceof PlayerActionPacket) {
			$player = $event->getPlayer ();
			if ($packet->action === PlayerActionPacket::ACTION_JUMP && isset ( $this->onChair [$player->getName ()] )) {
				$this->StandUp($player);
				unset ( $this->onChair [$player->getName ()] );
			}
		}
	}
	public function onQuit(PlayerQuitEvent $event) {
		$player = $event->getPlayer ();
		if (isset ( $this->onChair [$player->getName ()] )) {
			$this->StandUp($player);
			unset ( $this->onChair [$player->getName ()] );
		}
	}
}
