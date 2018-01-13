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

class PmChair extends PluginBase implements Listener {
	private $onChair = [ ];
	private $doubleTap = [ ];
	private $messages;
	
	const m_version = 1;
	
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->loadMessage();
	}
	public function loadMessage() {
		$this->saveResource("messages.yml");
		$this->messages = (new Config($this->getDataFolder()."messages.yml", Config::YAML, [ ]))->getAll();
		$this->updateMessage();
	}
	public function updateMessage() {
		if($this->messages['m_version'] < self::m_version) {
			$this->saveResource("messages.yml", true);
			$this->messages = (new Config($this->getDataFolder()."messages.yml", Config::YAML, [ ]))->getAll();
		}
	}
	public function get($m) {
		return $this->messages[$this->messages['default-language'].'-'.$m];
	}
	public function onTouch(PlayerInteractEvent $event) {
		$player = $event->getPlayer ();
		$block = $event->getBlock ();
		if (! isset ( $this->onChair [$player->getName ()] )) {
			if ($block instanceof Stair) {
				if (! isset ( $this->doubleTap [$player->getName ()] )) {
					$this->doubleTap [$player->getName ()] = $this->_microtime ();
					$player->sendPopup ( TextFormat::RED . $this->get("touch-popup"));
					return;
				}
				if ($this->_microtime ( true ) - $this->doubleTap [$player->getName ()] < 0.5) {
					
					$pk = new AddEntityPacket();
					$pk->entityRuntimeId = $this->player->getId() + 10000;
					$pk->type = 84;
					$pk->position = new Vector3($this->player->x,$this->player->y+0.5,$this->player->z);
					$pk->motion = new Vector3(0,0,0);
					$flags = (
						(1 << Entity::DATA_FLAG_IMMOBILE) |
					(1 << Entity::DATA_FLAG_INVISIBLE)
 					);
 					$pk->metadata = [
 						Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, $flags],
 					];
 					$pk->links[] = [$pk->entityRuntimeId,$this->player->getId(),Server::getInstance()->broadcastPacket(Server::getInstance()->getOnlinePlayers(), $pk);
				} else {
					$this->doubleTap [$player->getName ()] = $this->_microtime ();
					$player->sendPopup ( TextFormat::RED . $this->get("touch-popup") );
					return;
				}
			}
		} else {
			$removeEntityPacket = new RemoveEntityPacket ();
			$removeEntityPacket->entityUniqueId = $this->onChair [$player->getName ()];
			$this->getServer ()->broadcastPacket ( $this->getServer ()->getOnlinePlayers (), $removeEntityPacket );
			unset ( $this->onChair [$player->getName ()] );
		}
	}
	public function _microtime () { 
		return array_sum(explode(' ',microtime()));
	}
	public function onJump(DataPacketReceiveEvent $event) {
		$packet = $event->getPacket ();
		if (! $packet instanceof PlayerActionPacket) {
			return;
		}
		$player = $event->getPlayer ();
		if ($packet->action === PlayerActionPacket::ACTION_JUMP && isset ( $this->onChair [$player->getName ()] )) {
			$removepk = new RemoveEntityPacket();
			$removepk->entityUniqueId = $this->onChair [$player->getName ()];
			$this->getServer ()->broadcastPacket ( $this->getServer ()->getOnlinePlayers (), $removepk );
			unset ( $this->onChair [$player->getName ()] );
		}
	}
	public function onQuit(PlayerQuitEvent $event) {
		$player = $event->getPlayer ();
		if (! isset ( $this->onChair [$player->getName ()] )) {
			return;
		}
		$removepk = new RemoveEntityPacket ();
		$removepk->entityUniqueId = $this->onChair [$player->getName ()];
		$this->getServer ()->broadcastPacket ( $this->getServer ()->getOnlinePlayers (), $removepk );
		unset ( $this->onChair [$player->getName ()] );
	}
}
