<?php namespace Taco\HCF\other\entities;

use pocketmine\block\Block;
use pocketmine\block\FenceGate;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Projectile;
use pocketmine\entity\projectile\Throwable;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

use pocketmine\player\Player;
use pocketmine\utils\Random;
use pocketmine\world\sound\EndermanTeleportSound;
use pocketmine\world\World;
use Taco\HCF\Main;
use function in_array;
use function sqrt;

class HCFPearl extends Throwable {

    protected $gravity = 0.08;

    public function __construct(Location $level, ?CompoundTag $nbt, ?Entity $owner = null) {
        parent::__construct($level, $owner, $nbt);
        if($owner instanceof Player){
            $this->setPosition($this->getPosition()->add(0, $owner->getEyeHeight(), 0));
            $this->setMotion($owner->getDirectionVector()->multiply(1.5));
            $this->handleMotion($this->motion->x, $this->motion->y, $this->motion->z, 0.7, 1);
        }
    }
    protected function initEntity(CompoundTag $nbt): void
	{
		parent::initEntity($nbt); // TODO: Change the autogenerated stub
	}

	public function getResultDamage() : int{
        return 0;
    }

    public function calculateInterceptWithBlock(Block $block, Vector3 $start, Vector3 $end) : ?RayTraceResult {
        if ($block instanceof FenceGate && ($block->getMeta() & 0x04) > 0 && in_array(($block->getMeta() & 0x03), [0, 2])) {
            return null;
        } else {
            return $block->calculateIntercept($start, $end);
        }
    }

    protected function onHit(ProjectileHitEvent $event) : void{
        $owner = $this->getOwningEntity();
        if ($owner instanceof Player) {
            $where = $event->getRayTraceResult()->getHitVector();
            if (in_array(Main::getClaimManager()->getClaimAtPosition($where), Main::NON_DEATHBAN_CLAIMS) and Main::getInstance()->players[$owner->getName()]["timers"]["spawntag"] > 0) {
                $owner->sendMessage("§eYou cant pearl into spawn while spawn tagged!");
                $this->isCollided = true;
                return;
            }
            $this->getPosition()->getWorld()->addSound($owner->getPosition(), new EndermanTeleportSound());
            $owner->teleport($where);
            $owner->attack(new EntityDamageEvent($owner, EntityDamageEvent::CAUSE_PROJECTILE, 0));
            $this->getPosition()->getWorld()->addSound($owner->getPosition(), new EndermanTeleportSound());
            if ($event instanceof ProjectileHitEntityEvent) {
                $player = $event->getEntityHit();
                if ($player instanceof Player) {
                    if ($player->getId() != $owner->getId()) {
                        $event = new EntityDamageEvent($player, EntityDamageEvent::CAUSE_PROJECTILE, 1);
                        $event->call();
                    }
                }
            }
            $this->isCollided = true;
        }
    }
    public function handleMotion(float $x, float $y, float $z, float $f1, float $f2) : void {
        $rand = new Random();
        $f = sqrt($x * $x + $y * $y + $z * $z);
        $x = $x / $f;
        $y = $y / $f;
        $z = $z / $f;
        $x = $x + $rand->nextSignedFloat() * 0.007499999832361937 * (float)$f2;
        $y = $y + $rand->nextSignedFloat() * 0.008599999832361937 * (float)$f2;
        $z = $z + $rand->nextSignedFloat() * 0.007499999832361937 * (float)$f2;
        $x = $x * $f1;
        $y = $y * $f1;
        $z = $z * $f1;
        $this->motion->x += $x;
        $this->motion->y += $y;
        $this->motion->z += $z;
    }
    public function entityBaseTick(int $tickDiff = 1) : bool {
        $hasUpdate = parent::entityBaseTick($tickDiff);
        $owner = $this->getOwningEntity();
        if($this->isCollided or $owner === null or !$owner->isAlive() or $owner->isClosed()){
            $this->flagForDespawn();
        }
        return $hasUpdate;
    }



	public static function getNetworkTypeId() : string {
		return EntityIds::ENDER_PEARL;
	}

}