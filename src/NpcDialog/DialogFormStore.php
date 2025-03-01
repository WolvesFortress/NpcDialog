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
use pocketmine\entity\Entity;
use function array_key_exists;

class DialogFormStore{

	/** @var DialogForm[] */
	static private array $forms = [];

	static public function getFormByEntity(Entity $entity) : ?DialogForm{//FIXME this might be multible forms
		foreach(self::$forms as $form){
			if($form->getEntity() === $entity){
				return $form;
			}
		}
		return null;
	}

	static public function getFormById(string $id) : ?DialogForm{
		return self::$forms[$id] ?? null;
	}

	/** @throws InvalidArgumentException */
	static public function registerForm(DialogForm &$form, bool $overwrite = false) : void{
		if(!$overwrite && array_key_exists($form->getId(), self::$forms)){
			throw new InvalidArgumentException("Trying to overwrite an already registered npc form");
		}
		var_dump("Registering form: " . $form->getId());
		self::$forms[$form->getId()] = &$form;
	}

	/** @throws InvalidArgumentException */
	static public function unregisterForm(DialogForm $form) : void{
		if(array_key_exists($form->getId(), self::$forms)){
			unset(self::$forms[$form->getId()]);
		}else{
			throw new InvalidArgumentException("Tried to unregister a dialog form that wasn't registered");
		}
	}

}