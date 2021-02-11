<?php /** @noinspection PhpInternalEntityUsedInspection */
declare(strict_types=1);

namespace kim\present\batchfarming\entity;

use pocketmine\block\Block;
use pocketmine\block\BlockLegacyIds;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\world\World;

/**
 * Abstract class of entities falling into blocks of specified height.
 * Re-written for call events via owner player.
 * When it drop an item, drop ReturnItemEntity instead ItemEntity.
 */
abstract class TargetingFallingEntity extends Entity{
    /**
     * Counts of entities summoned by the each player
     * It is used to disable continuous use when a function is already in use.
     *
     * @var int[] string $playerHash => int $count
     */
    protected static array $counts = [];

    public static function getCount(Player $player) : int{
        return self::$counts[spl_object_hash($player)] ?? 0;
    }

    protected Player $owningPlayer;
    protected int $targetY;

    public function __construct(Location $location, Player $owningPlayer, int $targetY, ?CompoundTag $nbt = null){
        parent::__construct($location, $nbt);
        $this->gravity = 0.04;
        $this->drag = 0.02;

        $this->owningPlayer = $owningPlayer;
        $this->targetY = $targetY;

        if(!isset(self::$counts[$hash = spl_object_hash($owningPlayer)])){
            self::$counts[$hash] = 0;
        }
        self::$counts[$hash]++;
    }

    /** @override for working like falling block */
    protected function entityBaseTick(int $tickDiff = 1) : bool{
        if($this->closed)
            return false;

        $hasUpdate = parent::entityBaseTick($tickDiff);

        if($this->owningPlayer->isClosed() || !$this->owningPlayer->isConnected()){
            $this->kill();
        }elseif(!$this->isFlaggedForDespawn() && $this->onGround){
            if(!$this->run($this->getWorld(), $this->location->add(-0.5, -1, -0.5)->floor())){
                //If the features fails to run, kill the entity to drop the item.
                $this->kill();
            }

            $this->flagForDespawn();
            $hasUpdate = true;
        }

        return $hasUpdate;
    }

    /** @override for pass when more than 2 spaces higher than the target y */
    protected function move(float $dx, float $dy, float $dz) : void{
        $this->keepMovement = $this->location->y - $this->targetY > 2;
        parent::move($dx, $dy, $dz);
    }

    /** @override for drop item */
    protected function onDeath() : void{
        $event = new PlayerDropItemEvent($this->owningPlayer, $this->getItem());
        $event->call();

        $item = $event->getItem();
        $inv = $this->owningPlayer->getInventory();
        if(!$event->isCancelled() || !$this->owningPlayer->hasFiniteResources() || !empty($inv->addItem($item))){
            $itemEntity = new ReturnItemEntity(Location::fromObject($this->location, $this->getWorld()), $item);
            $itemEntity->setOwningEntity($this->owningPlayer);
            $itemEntity->setPickupDelay(10);
            $itemEntity->setMotion($motion ?? new Vector3(lcg_value() * 0.2 - 0.1, 0.2, lcg_value() * 0.2 - 0.1));
            $itemEntity->spawnToAll();
        }
    }

    /** @override for reduce summon count */
    protected function onDispose() : void{
        self::$counts[$hash = spl_object_hash($this->owningPlayer)]--;
        if(self::$counts[$hash] <= 0){
            unset(self::$counts[$hash]);
        }
        parent::onDispose();
    }

    /** @override for prevent damage */
    public function attack(EntityDamageEvent $source) : void{
        if($source->getCause() !== EntityDamageEvent::CAUSE_VOID){
            $source->cancel();
        }
    }

    /** @override for prevent save to chunk */
    public function canSaveWithChunk() : bool{
        return false;
    }

    /** @override for fix position */
    public function getOffsetPosition(Vector3 $vector3) : Vector3{
        return $vector3->add(0, 0.5, 0);
    }

    /** @override for prevent interact from entity or blocks */
    protected function getInitialSizeInfo() : EntitySizeInfo{
        return new EntitySizeInfo(0.001, 0.001);
    }

    /** Returns item */
    abstract public function getItem() : Item;

    /**
     * Run features to given vector.
     *
     * @return bool If the features fails to run, it returns false
     */
    protected function run(World $world, Vector3 $vector) : bool{
        $blockClicked = $world->getBlock($vector);
        if($blockClicked->getId() === BlockLegacyIds::AIR)
            return false;

        $blockReplace = $blockClicked->getSide(Facing::UP);
        $replaceVector = $blockReplace->getPos();
        if(!$world->isInWorld($replaceVector->x, $replaceVector->y, $replaceVector->z))
            return false;

        $chunkX = $replaceVector->getFloorX() >> 4;
        $chunkZ = $replaceVector->getFloorZ() >> 4;
        if(!$world->isChunkLoaded($chunkX, $chunkZ) || !$world->isChunkGenerated($chunkX, $chunkZ) || $world->isChunkLocked($chunkX, $chunkZ))
            return false;

        return $this->onRun($world, $blockClicked, $blockReplace);
    }

    abstract protected function onRun(World $world, Block $blockClicked, Block $blockReplace) : bool;
}