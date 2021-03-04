<?php

/**
 *  ____                           _   _  ___
 * |  _ \ _ __ ___  ___  ___ _ __ | |_| |/ (_)_ __ ___
 * | |_) | '__/ _ \/ __|/ _ \ '_ \| __| ' /| | '_ ` _ \
 * |  __/| | |  __/\__ \  __/ | | | |_| . \| | | | | | |
 * |_|   |_|  \___||___/\___|_| |_|\__|_|\_\_|_| |_| |_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author  PresentKim (debe3721@gmail.com)
 * @link    https://github.com/PresentKim
 * @license https://www.gnu.org/licenses/lgpl-3.0 LGPL-3.0 License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 *
 * @noinspection PhpIllegalPsrClassPathInspection
 * @noinspection PhpDocSignatureInspection
 * @noinspection SpellCheckingInspection
 * @noinspection PhpInternalEntityUsedInspection
 */

declare(strict_types=1);

namespace kim\present\batchfarming\object;

use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\IntMetadataProperty;
use pocketmine\player\Player;
use pocketmine\world\sound\BlockPlaceSound;
use pocketmine\world\World;

final class SeedObject extends Vector3{
    public Block $block;
    public int $entityRuntimeId;
    public float $motionY = 0.0;

    public function __construct(Vector3 $parent, Block $block){
        parent::__construct($parent->x, $parent->y, $parent->z);
        $this->block = $block;
        $this->entityRuntimeId = Entity::nextRuntimeId();
    }

    public function getSpawnPacket() : AddActorPacket{
        $pk = new AddActorPacket();
        $pk->entityRuntimeId = $this->entityRuntimeId;
        $pk->position = $this;
        $pk->type = EntityIds::FALLING_BLOCK;
        $pk->metadata = [
            EntityMetadataProperties::VARIANT => new IntMetadataProperty(RuntimeBlockMapping::getInstance()->toRuntimeId($this->block->getFullId())),
        ];
        return $pk;
    }

    public function getDespawnPacket() : RemoveActorPacket{
        return RemoveActorPacket::create($this->entityRuntimeId);
    }

    public function getMovementPacket() : MoveActorAbsolutePacket{
        return MoveActorAbsolutePacket::create($this->entityRuntimeId, $this->add(0, 0.5, 0), 0, 0, 0);
    }

    /** @return bool Whether block placement is successful */
    public function place(World $world, Player $player) : bool{
        $clicked = $this->floor();
        $replace = $clicked->add(0, 1, 0);
        $blockReplace = $world->getBlock($replace);
        if(
            !$world->isInWorld($replace->x, $replace->y, $replace->z) ||
            !$world->isChunkLoaded($chunkX = $replace->getFloorX() >> 4, $chunkZ = $replace->getFloorZ() >> 4) ||
            !$world->isChunkGenerated($chunkX, $chunkZ) ||
            $world->isChunkLocked($chunkX, $chunkZ) ||
            !$this->block->canBePlacedAt($blockReplace, new Vector3(0.5, 0, 0.5), Facing::UP, false)
        ){
            return false;
        }

        $ev = new BlockPlaceEvent($player, $this->block, $blockReplace, $world->getBlock($clicked), $this->block->getPickedItem());
        $ev->call();
        if($ev->isCancelled())
            return false;

        $world->setBlockAt($replace->x, $replace->y, $replace->z, $this->block);
        $world->addSound($replace, new BlockPlaceSound($this->block));
        return true;
    }

    public function collect(World $world, Player $player) : void{
        $item = $this->block->getPickedItem();
        if(
            $player->isClosed() ||
            !$player->isConnected() ||
            !empty($player->getInventory()->addItem($item))
        ){
            $world->dropItem($this->add(0, 0.5, 0), $item);
        }
    }
}