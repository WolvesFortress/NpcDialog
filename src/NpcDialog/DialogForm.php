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
use DaveRandom\CallbackValidator\CallbackType;
use DaveRandom\CallbackValidator\InvalidCallbackException;
use DaveRandom\CallbackValidator\ParameterType;
use DaveRandom\CallbackValidator\ReturnType;
use InvalidArgumentException;
use LogicException;
use pocketmine\entity\Entity;
use pocketmine\form\FormValidationException;
use pocketmine\network\mcpe\protocol\NpcDialoguePacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\utils\Utils;
use Ramsey\Uuid\Uuid;
use TypeError;
use function array_key_exists;
use function json_encode;

class DialogForm{
	private string $id;

	/** @var Button[] */
	private array $buttons = [];

	private ?Entity $entity = null;

	private ?Closure $closeListener = null;
	private ?Closure $openListener = null;

	/**
	 * @param null|Closure(Player $player) : void $openListener
	 * @param null|Closure(Player $player) : void $closeListener
	 *
	 * @throws TypeError|InvalidCallbackException|InvalidArgumentException
	 */
	public function __construct(private string $dialogText, ?Closure $openListener = null, ?Closure $closeListener = null, ?string $id = null, private bool $closeOnSubmit = true, bool $overwrite = false){
		$this->id = $id ?? Uuid::uuid4()->toString();
		$this->setOpenListener($openListener);
		$this->setCloseListener($closeListener);
		DialogFormStore::registerForm($this, $overwrite);

		$this->onCreation();
	}

	public function getId() : string{ return $this->id; }

	public function getDialogText() : string{
		return $this->dialogText;
	}

	/** @return $this */
	public function setDialogText(string $dialogText) : self{
		$this->dialogText = $dialogText;
		return $this;
	}

	/**
	 * @param null|Closure(Player $player) : bool $submitListener
	 * @return $this
	 * @throws InvalidCallbackException|TypeError
	 */
	public function addButton(string $name = "", string $command = "", ?Closure $submitListener = null) : self{
		if($submitListener !== null){
			Utils::validateCallableSignature(new CallbackType(
				new ReturnType("bool"),
				new ParameterType("player", Player::class)
			), $submitListener);
		}
		$this->buttons[] = new Button($name, $command, $submitListener);
		return $this;
	}

	public function getActions() : string{//aka the buttons
		return json_encode(array_values($this->buttons));
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

	/**
	 * @param null|Closure(Player $player) : void $closeListener
	 * @return $this
	 * @throws InvalidCallbackException|TypeError
	 */
	public function setCloseListener(?Closure $closeListener) : self{
		if($closeListener !== null){
			Utils::validateCallableSignature(new CallbackType(
				new ReturnType("void"),
				new ParameterType("player", Player::class)
			), $closeListener);
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

	/**
	 * @param null|Closure(Player $player) : void $openListener
	 * @return $this
	 * @throws InvalidCallbackException|TypeError
	 */
	public function setOpenListener(?Closure $openListener) : self{
		if($openListener !== null){
			Utils::validateCallableSignature(new CallbackType(
				new ReturnType("void"),
				new ParameterType("player", Player::class)
			), $openListener);
		}
		$this->openListener = $openListener;

		return $this;
	}

	public function executeOpenListener(Player $player) : void{
		if($this->openListener !== null){
			($this->openListener)($player);
		}
	}

	/** @throws FormValidationException|LogicException */
	public function executeButtonSubmitListener(Player $player, int $button) : void{
		if(array_key_exists($button, $this->buttons)){
			$close = $this->buttons[$button]->executeSubmitListener($player);
			// Close form after submit, otherwise the player is stuck in the form
			// It's also possible to resend the form with modified data without having to close it by using the same ID
			if($close || $this->closeOnSubmit){
				$this->close($player);
			}
		}else{
			// Close the form on error anyways
			$this->close($player);
			throw new FormValidationException("Couldn't validate DialogForm with response $button: button doesn't exist.");
		}
	}

	/** @return $this */
	public function pairWithEntity(Entity $entity, string $interactiveTag = "") : self{
		//TODO check if we can pair the form to multiple entities

		$this->setEntity($entity);
		$this->entity->getNetworkProperties()->setString(EntityMetadataProperties::INTERACTIVE_TAG, $interactiveTag);

		return $this;
	}

	protected function onCreation() : void{ }

	/** @throws InvalidArgumentException|LogicException */
	public function open(Player $player, ?int $eid = null, ?string $nametag = null) : void{
		if(($otherForm = DialogFormStore::getFormByEntity($player)) !== null && $otherForm !== $this && $player !== $this->entity){
			var_dump("Form already paired with another entity: " . $player->getId() . " vs " . ($this->entity?->getId() !== null ? $this->entity->getId() : "null"));
			DialogFormStore::unregisterForm($otherForm);
		}
		$pk = NpcDialoguePacket::create($eid ?? $this->entity?->getId() ?? $player->getId(), NpcDialoguePacket::ACTION_OPEN, $this->getDialogText(), $this->getId(), $nametag ?? $this->entity?->getNameTag() ?? $player->getNameTag(), $this->getActions());
		$player->getNetworkSession()->sendDataPacket($pk);
	}

	/** @throws LogicException */
	public function close(Player $player) : void{
		$pk = NpcDialoguePacket::create($this->entity?->getId() ?? $player->getId(), NpcDialoguePacket::ACTION_CLOSE, "", "", "", "");
		$player->getNetworkSession()->sendDataPacket($pk);
	}

	private function setEntity(Entity $entity = null) : void{
		$this->entity?->getNetworkProperties()->setByte(EntityMetadataProperties::HAS_NPC_COMPONENT, 0);
		$this->entity?->getNetworkProperties()->setString(EntityMetadataProperties::NPC_ACTIONS, "");
		$this->entity = $entity;
		$this->entity?->getNetworkProperties()->setByte(EntityMetadataProperties::HAS_NPC_COMPONENT, 1);
		$this->entity?->getNetworkProperties()->setString(EntityMetadataProperties::NPC_ACTIONS, $this->getActions());
	}
}