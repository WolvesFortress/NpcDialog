<?php
/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace NpcDialog;

use Closure;
use pocketmine\entity\Entity;
use pocketmine\form\FormValidationException;
use pocketmine\network\mcpe\protocol\NpcDialoguePacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\utils\Utils;
use Ramsey\Uuid\Uuid;
use function array_key_exists;
use function json_encode;

class DialogForm{
	private string $id;

	/** @var Button[] */
	private array $buttons = [];

	private ?Entity $entity = null;

	private ?Closure $closeListener = null;
	private ?Closure $openListener = null;

	public function __construct(private string $dialogText, ?Closure $openListener = null, ?Closure $closeListener = null, ?string $id = null, private bool $closeOnSubmit = true){
		$this->id = $id ?? Uuid::uuid4()->toString();
		$this->setOpenListener($openListener);
		$this->setCloseListener($closeListener);
		DialogFormStore::registerForm($this);

		$this->onCreation();
	}

	public function getId() : string{ return $this->id; }

	public function getDialogText() : string{
		return $this->dialogText;
	}

	/** @return $this */
	public function setDialogText(string $dialogText) : self{
		$this->dialogText = $dialogText;

		$this->entity?->getNetworkProperties()->setString(EntityMetadataProperties::INTERACTIVE_TAG, $this->dialogText);
		return $this;
	}

	/** @return $this */
	public function addButton(string $name = "", string $command = "", ?Closure $submitListener = null) : self{
		$this->buttons[] = new Button($name, $command, $submitListener);
		return $this;
	}

	public function getActions() : string{//aka the buttons
		return json_encode($this->buttons);
	}

	public function getEntity() : ?Entity{
		return $this->entity;
	}

	public function isClosingOnSubmit() : bool{
		return $this->closeOnSubmit;
	}

	/** @return $this */
	public function setCloseOnSubmit(bool $closeOnSubmit = true) : self{
		$this->closeOnSubmit = $closeOnSubmit;
		return $this;
	}

	public function getCloseListener() : ?Closure{
		return $this->closeListener;
	}

	/** @return $this */
	public function setCloseListener(?Closure $closeListener) : self{
		if($closeListener !== null){
			Utils::validateCallableSignature(function(Player $player){ }, $closeListener);
		}
		$this->closeListener = $closeListener;

		return $this;
	}

	public function executeCloseListener(Player $player) : void{
		if($this->closeListener !== null){
			($this->closeListener)($player);
		}
	}

	public function getOpenListener() : ?Closure{
		return $this->openListener;
	}

	public function setOpenListener(?Closure $openListener) : self{
		if($openListener !== null){
			Utils::validateCallableSignature(function(Player $player){ }, $openListener);
		}
		$this->openListener = $openListener;

		return $this;
	}

	public function executeOpenListener(Player $player) : void{
		if($this->openListener !== null){
			($this->openListener)($player);
		}
	}

	public function executeButtonSubmitListener(Player $player, int $button) : void{
		if(array_key_exists($button, $this->buttons)){
			$this->buttons[$button]->executeSubmitListener($player);
			// Close form after submit, otherwise the player is stuck in the form
			// It's also possible to resend the form with modified data without having to close it by using the same ID
			if($this->closeOnSubmit){
				$this->close($player);
			}
		}else{
			// Close the form on error anyways
			$this->close($player);
			throw new FormValidationException("Couldn't validate DialogForm with response $button: button doesn't exist.");
		}
	}

	/** @return $this */
	public function pairWithEntity(Entity $entity) : self{
		$this->entity?->getNetworkProperties()->setByte(EntityMetadataProperties::HAS_NPC_COMPONENT, 0);

		if(($otherForm = DialogFormStore::getFormByEntity($entity)) !== null){
			DialogFormStore::unregisterForm($otherForm);
		}

		$this->entity = $entity;

		$propertyManager = $entity->getNetworkProperties();
		$propertyManager->setByte(EntityMetadataProperties::HAS_NPC_COMPONENT, 1);
		$propertyManager->setString(EntityMetadataProperties::INTERACTIVE_TAG, $this->dialogText);
		$propertyManager->setString(EntityMetadataProperties::NPC_ACTIONS, $this->getActions());

		return $this;
	}

	protected function onCreation() : void{ }

	public function open(Player $player, ?int $eid = null, ?string $nametag = null) : void{
		$pk = NpcDialoguePacket::create($eid ?? $this->entity->getId(), NpcDialoguePacket::ACTION_OPEN, $this->getDialogText(), $this->getId(), $nametag ?? $this->entity->getNameTag(), $this->getActions());
		$player->getNetworkSession()->sendDataPacket($pk);
	}

	public function close(Player $player) : void{
		$pk = NpcDialoguePacket::create($this->entity->getId(), NpcDialoguePacket::ACTION_CLOSE, $this->getDialogText(), $this->getId(), $this->entity->getNameTag(), $this->getActions());
		$player->getNetworkSession()->sendDataPacket($pk);
	}
}