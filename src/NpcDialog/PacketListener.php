<?php
/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace NpcDialog;

use InvalidArgumentException;
use LogicException;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerEntityInteractEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\form\FormValidationException;
use pocketmine\network\mcpe\protocol\NpcDialoguePacket;
use pocketmine\network\mcpe\protocol\NpcRequestPacket;
use pocketmine\Server;
use RuntimeException;

class PacketListener implements Listener{

	/** @throws FormValidationException|LogicException|RuntimeException */
	public function onPacketReceiveEvent(DataPacketReceiveEvent $event) : void{
		$packet = $event->getPacket();
		if($packet instanceof NpcRequestPacket){
			$this->handleNpcRequest($event);
		}
	}

	/** @throws LogicException|RuntimeException|FormValidationException */
	private function handleNpcRequest(DataPacketReceiveEvent $event) : void{
		/** @var NpcRequestPacket $packet */
		if(($packet = $event->getPacket()) instanceof NpcRequestPacket){
			$server = Server::getInstance();
			$player = $event->getOrigin()->getPlayer();
			$entity = $server->getWorldManager()->findEntity($packet->actorRuntimeId);
			if($entity === null) return;

			$username = $player->getName();
			$logger = $server->getLogger();

			$logger->debug("Received NpcRequestPacket from $username");
			$logger->debug("NpcRequestPacket request type: " . $packet->requestType . " action index: " . $packet->actionIndex . " command: " . $packet->commandString . " runtime id: " . $packet->actorRuntimeId . " scene name: " . $packet->sceneName);

			switch($packet->requestType){
				case NpcRequestPacket::REQUEST_EXECUTE_ACTION:
					$logger->debug("Received a NpcRequestPacket action " . $packet->actionIndex);
					$form = DialogFormStore::getFormById($packet->sceneName);
					if($form !== null){
						$form->executeButtonSubmitListener($player, $packet->actionIndex);
					}else{
						$logger->warning("Unhandled NpcRequestPacket for $username because there wasn't a registered form on the store");
						//close the form
						$player->getNetworkSession()->sendDataPacket(NpcDialoguePacket::create($packet->actorRuntimeId, NpcDialoguePacket::ACTION_CLOSE, "", "", "", ""));
					}
					break;
				case NpcRequestPacket::REQUEST_EXECUTE_OPENING_COMMANDS:
					$logger->debug("Received a NpcRequestPacket action " . $packet->actionIndex);
					$form = DialogFormStore::getFormById($packet->sceneName);
					if($form !== null){
						$form->executeOpenListener($player);
					}else{
						$logger->warning("Unhandled NpcRequestPacket for $username because there wasn't a registered form on the store");
						//close the form
//						$player->getNetworkSession()->sendDataPacket(NpcDialoguePacket::create($packet->actorRuntimeId, NpcDialoguePacket::ACTION_CLOSE, "","","",""));
					}
					break;
				case NpcRequestPacket::REQUEST_EXECUTE_CLOSING_COMMANDS:
					$form = DialogFormStore::getFormById($packet->sceneName);
					if($form !== null){
						$form->executeCloseListener($player);
					}else{
						$logger->warning("Unhandled NpcRequestPacket for $username because there wasn't a registered form on the store");
						//close the form
						$player->getNetworkSession()->sendDataPacket(NpcDialoguePacket::create($packet->actorRuntimeId, NpcDialoguePacket::ACTION_CLOSE, "", "", "", ""));
					}
					break;
				default:
				{
					$logger->warning("Unhandled NpcRequestPacket for $username because the request type was unknown (unimplemented)");
				}
			}
		}
	}

	/** @throws InvalidArgumentException|LogicException|RuntimeException */
	public function onPlayerEntityInteractEvent(PlayerEntityInteractEvent $event) : void{
		$player = $event->getPlayer();
		$entity = $event->getEntity();
		$server = Server::getInstance();
		$logger = $server->getLogger();
		$username = $player->getName();
		$form = DialogFormStore::getFormByEntity($entity);
		if($form === null){
			return;
		}
		$logger->debug("Received PlayerEntityInteractEvent from $username for entity " . $entity->getNameTag() . " with id " . $entity->getId() . " that has a registered form");
		$form->open($player);
	}

}