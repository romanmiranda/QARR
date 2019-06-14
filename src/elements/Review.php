<?php
/**
 * QARR plugin for Craft CMS 3.x
 *
 * Questions & Answers and Reviews & Ratings
 *
 * @link      https://owl-design.net
 * @copyright Copyright (c) 2018 Vadim Goncharov
 */

namespace owldesign\qarr\elements;

use craft\commerce\elements\Product;
use craft\helpers\DateTimeHelper;
use craft\helpers\StringHelper;
use owldesign\qarr\QARR;
use owldesign\qarr\elements\db\ReviewQuery;
use owldesign\qarr\elements\actions\SetStatus;
use owldesign\qarr\elements\actions\Delete;
use owldesign\qarr\records\Review as ReviewRecord;
use owldesign\qarr\jobs\GeolocationTask;
use owldesign\qarr\jobs\RulesTask;

use Craft;
use craft\base\Element;
use craft\helpers\UrlHelper;
use craft\elements\db\ElementQueryInterface;
use craft\behaviors\FieldLayoutBehavior;

use craft\commerce\Plugin as CommercePlugin;
use yii\base\Exception;
use yii\validators\EmailValidator;

/**
 * Class Review
 * @package owldesign\qarr\elements
 */
class Review extends Element
{
    // Constants
    // =========================================================================

    /**
     *
     */
    const STATUS_PENDING    = 'pending';
    /**
     *
     */
    const STATUS_APPROVED   = 'approved';
    /**
     *
     */
    const STATUS_REJECTED   = 'rejected';

    // Properties
    // =========================================================================

    /**
     * @var
     */
    public $fullName;
    /**
     * @var
     */
    public $emailAddress;
    /**
     * @var
     */
    public $rating;
    /**
     * @var
     */
    public $feedback;
    /**
     * @var
     */
    public $response;
    /**
     * @var string
     */
    public $status = 'pending';
    /**
     * @var
     */
    public $options;
    /**
     * @var
     */
    public $hasPurchased;
    /**
     * @var
     */
    public $isNew;
    /**
     * @var
     */
    public $abuse;
    /**
     * @var
     */
    public $votes;
    /**
     * @var
     */
    public $display;
    /**
     * @var
     */
    public $displayId;
    /**
     * @var
     */
    public $displayHandle;
    /**
     * @var
     */
    public $elementId;
    /**
     * @var
     */
    public $productTypeId;
    /**
     * @var
     */
    public $geolocation;
    /**
     * @var
     */
    public $ipAddress;
    /**
     * @var
     */
    public $userAgent;

    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return QARR::t('Review');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle()
    {
        return 'qarrReview';
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasTitles(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getStatus()
    {
        if ($this->status == 'pending') {
            return self::STATUS_PENDING;
        }

        if ($this->status == 'approved') {
            return self::STATUS_APPROVED;
        }

        if ($this->status == 'rejected') {
            return self::STATUS_REJECTED;
        }

        return self::STATUS_PENDING;
    }

    /**
     * @inheritdoc
     */
    public static function find(): ElementQueryInterface
    {
        return new ReviewQuery(get_called_class());
    }

    // Public Methods
    // =========================================================================

    /**
     *
     */
    public function init()
    {
        parent::init();
        $this->setScenario(self::SCENARIO_LIVE);
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['fieldLayout'] = [
            'class' => FieldLayoutBehavior::class,
            'elementType' => Display::class
        ];
        return $behaviors;
    }

    /**
     * @inheritdoc
     */
    public function fieldLayoutFields(): array
    {
        $display = $this->getDisplay();

        if ($display) {
            $fieldLayout = $display->getFieldLayout();

            if ($fieldLayout) {
                return $fieldLayout->getFields();
            }
        }

        return [];
    }

    /**
     * Returns the field context this element's content uses.
     *
     * @access protected
     * @return string
     */
    public function getFieldContext(): string
    {
        return 'global';
    }

    /**
     * @inheritdoc
     */
    public function __toString()
    {
        return $this->title ?: ((string)$this->id ?: static::class);
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout()
    {
        if ($this->displayId !== null) {
            $display = $this->getDisplay();
            if ($display) {
                return $display->getFieldLayout();
            }
        }

        return null;
    }

    /**
     * @return mixed
     */
    public function getDisplay()
    {
        if ($this->displayId !== null) {
            $this->display = QARR::$plugin->displays->getDisplayById($this->displayId);
        }

        return $this->display;
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl(
            'qarr/reviews/'.$this->id
        );
    }

    /**
     * @inheritdoc
     */
    public function getUrl()
    {
        return UrlHelper::cpUrl(
            'qarr/reviews/'.$this->id
        );
    }

    /**
     * @inheritdoc
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING    => QARR::t('Pending'),
            self::STATUS_APPROVED   => QARR::t('Approved'),
            self::STATUS_REJECTED   => QARR::t('Rejected'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules      = parent::rules();
        $rules[]    = [['fullName', 'emailAddress', 'rating', 'feedback'], 'required'];
        $rules[]    = [['emailAddress'], EmailValidator::class];

        return $rules;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context = null): array
    {
        $sources = [
            [
                'key'   => '*',
                'label' => QARR::t('All Product Types')
            ]
        ];

        $productTypes = CommercePlugin::getInstance()->productTypes->getAllProductTypes();

        foreach ($productTypes as $type) {
            $key = 'type:' . $type->id;
            $sources[$key] = [
                'key'      => $key,
                'label'    => $type->name,
                'criteria' => ['productTypeId' => $type->id]
            ];
        }

        return $sources;
    }

    /**
     * @param string|null $source
     * @return array
     */
    protected static function defineActions(string $source = null): array
    {
        $actions[] = SetStatus::class;
        $actions[] = Delete::class;

        return $actions;
    }

    /**
     * @param string $attribute
     * @return string
     * @throws \yii\base\InvalidConfigException
     */
    protected function tableAttributeHtml(string $attribute): string
    {
        switch($attribute) {
            case 'flags':
                $flags = self::getFlags();
                $markup = '<div class="flags-container">';

                if ($flags) {
                    foreach ($flags as $flag) {
                        $markup .= '<div class="flags-wrapper">';
                        $markup .= '<div class="flagged-item"><i class="fal fa-'. $flag["rule"]["icon"] .'"></i> <span>'. $flag["rule"]["name"] .'</span></div>';
                        $markup .= '</div>';
                    }
                }
                return $markup;
                break;
            case 'guest':
                $markup = '<div class="guest-wrapper">';
                $markup .= '<div class="guest-meta"><span class="guest-name">'.$this->fullName.'</span><span class="guest-email">'.$this->emailAddress.'</span></div>';
                $markup .= '</div>';
                return $markup;
                break;
            case 'elementId':
//                $product = CommercePlugin::getInstance()->products->getProductById($this->productId);
//                if (!$product) {
//                    return '<p>'.QARR::t('Commerce Plugin is required!').'</p>';
//                }
//                $markup = '<div class="product-wrapper">';
//                $markup .= '<div class="product-meta">';
//                $markup .= '<span class="product-name">'.$product->title.'</span><span class="product-type">'.$product->getType()->name.'</span>';
//                $markup .= '</div>';
//                $markup .= '</div">';
//                return $markup;
                return 'Element';
                break;
            case 'rating':
                $rating = (int)$this->rating;
                $markup = '<div class="rating-wrapper">';
                for ($i = 1; $i <= 5; $i++) {
                    if ($rating >= $i) {
                        $markup .= '<span class="qarr-rating-star active"><i class="fa fa-star"></i></span>';
                    } else {
                        $markup .= '<span class="qarr-rating-star"><i class="fa fa-star"></i></span>';
                    }
                }
                $markup .= '</div>';
                return $markup;
                break;
//            case 'actions':
//                $editUrl = UrlHelper::cpUrl('qarr/reviews/'.$this->id);
//                $markup = '<a href="'.$editUrl.'">View</a>';
//                return $markup;
//                break;
            case 'feedback':
                $markup = '<div class="feedback-wrapper"><span>'.StringHelper::truncateWords($this->feedback, 3).'</span></div>';
                return $markup;
                break;
            case 'dateCreated':
                $date = DateTimeHelper::toIso8601($this->dateCreated);
                $markup = '<div class="date-wrapper"><span>'.$this->getTimeAgo($date) . ' ago</span></div>';
                return $markup;
                break;
            default:
                return parent::tableAttributeHtml($attribute);
                break;
        }
    }

    /**
     * @return array
     */
    protected static function defineSortOptions(): array
    {
        $attributes = [
            'qarr_reviews.status'       => QARR::t('Status'),
            'qarr_reviews.rating'       => QARR::t('Rating'),
            'qarr_reviews.fullName'     => QARR::t('Customer'),
            'qarr_reviews.elementId'    => QARR::t('Element'),
            'elements.dateCreated'      => QARR::t('Submitted')
        ];

        return $attributes;
    }

    /**
     * @return array
     */
    protected static function defineTableAttributes(): array
    {
        $attributes = [];
        $attributes['status'] = ['label' => QARR::t('Title')];
        $attributes['flags'] = ['label' => QARR::t('Flags')];
        $attributes['guest'] = ['label' => QARR::t('Guest')];
        $attributes['rating'] = ['label' => QARR::t('Rating')];
        $attributes['feedback'] = ['label' => QARR::t('Feedback')];
        $attributes['elementId'] = ['label' => QARR::t('Element')];
        $attributes['dateCreated'] = ['label' => QARR::t('Submitted')];

        return $attributes;
    }

    /**
     * @param string $source
     * @return array
     */
    public static function defaultTableAttributes(string $source): array
    {
        return ['status', 'guest', 'rating', 'elementId', 'dateCreated'];
    }

    /**
     * @return \craft\commerce\elements\Product|null|string
     */
//    public function product()
//    {
//        $product = CommercePlugin::getInstance()->products->getProductById($this->productId);
//
//        if (!$product) {
//            $product = new Product();
//            return $product;
////            Craft::dd($product);
////            return '<p>'.QARR::t('Product not found!').'</p>';
//        }
//
//        return $product;
//    }

    /**
     * @return mixed
     */
    public function getReply()
    {
        $response = QARR::$plugin->replies->getReply($this->id);

        return $response;
    }

    /**
     * @return mixed
     */
    public function getCustomer()
    {
        $customer = null;
        $user = Craft::$app->users->getUserByUsernameOrEmail($this->emailAddress);

        if ($user) {
            $customer = CommercePlugin::getInstance()->customers->getCustomerByUserid($user->id);
        }

        return $customer;
    }

    /**
     * @param $time
     * @return string
     */
    public function getTimeAgo($time)
    {
        $periods = array("second", "minute", "hour", "day", "week", "month", "year", "decade");
        $lengths = array("60","60","24","7","4.35","12","10");

        $now = time();
        $difference     = $now - strtotime($time);

        for($j = 0; $difference >= $lengths[$j] && $j < count($lengths)-1; $j++) {
            $difference /= $lengths[$j];
        }

        $difference = round($difference);

        if($difference != 1) {
            $periods[$j].= "s";
        }

        return "$difference $periods[$j]";
    }

    public function getFlags()
    {
        $result = QARR::$plugin->rules->getFlagged($this->id);

        return $result;
    }

    // Events
    // -------------------------------------------------------------------------

    /**
     * @param bool $isNew
     * @return bool
     */
    public function beforeSave(bool $isNew): bool
    {
        return true;
    }

    /**
     * @param bool $isNew
     * @throws Exception
     */
    public function afterSave(bool $isNew)
    {

        if (!$isNew) {
            $record = ReviewRecord::findOne($this->id);

            if (!$record) {
                throw new Exception('Invalid Review ID: '.$this->id);
            }
        } else {
            $record = new ReviewRecord();
            $record->id = $this->id;
        }

        $record->fullName       = $this->fullName;
        $record->emailAddress   = $this->emailAddress;
        $record->feedback       = $this->feedback;
        $record->rating         = $this->rating;
        $record->status         = $this->status;
        $record->options        = $this->options;
        $record->displayId      = $this->displayId;
        $record->elementId      = $this->elementId;
        $record->productTypeId  = $this->productTypeId;
        $record->ipAddress      = $this->ipAddress;
        $record->userAgent      = $this->userAgent;

        $record->save(false);

        if ($isNew) {
            // Apply Rule
            Craft::$app->getQueue()->push(new RulesTask([
                'entry' => $record,
            ]));

            // Apply Geolocation
            Craft::$app->getQueue()->push(new GeolocationTask([
                'ipAddress' => $this->ipAddress,
                'elementId' => $this->id,
                'table' => '{{%qarr_reviews}}'
            ]));
        }

        parent::afterSave($isNew);
    }

    /**
     * @return bool
     */
    public function beforeDelete(): bool
    {
        return true;
    }

    /**
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function afterDelete()
    {
        $record = ReviewRecord::findOne($this->id);
        $record->delete();

        // Remove rules
        QARR::$plugin->rules->removeRecord($this->id);
    }

    /**
     * @param int $structureId
     * @return bool
     */
    public function beforeMoveInStructure(int $structureId): bool
    {
        return true;
    }

    /**
     * @param int $structureId
     */
    public function afterMoveInStructure(int $structureId)
    {
    }
}
