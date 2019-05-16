<?php defined('MW_PATH') || exit('No direct script access allowed');

class PanoplySubscriber
{
    public function save($result, $list_id)
    {
        $subscriber = $this->existingOrNewSubscriber($result, $list_id);
        $new = !$subscriber->subscriber_id;

        $subscriber->list_id = $list_id;
        $subscriber->email = $result->email;
        $subscriber->ip_address = $result->ip_address;
        $subscriber->source = $result->source;
        $subscriber->status = $result->status;
        $status = $subscriber->save();

        if ($new) {
            $this->storeListFieldValues($subscriber, $result);
        }
        
        if ($result->date_added) {
            $subscriber->date_added = $result->date_added;
        }
        if ($result->last_updated) {
            $subscriber->last_updated = $result->last_updated;
        }
        if ($result->date_added || $result->last_updated) {
            // override NOW() values with custom ones
            $subscriber->save();
        }
    }

    protected function existingOrNewSubscriber($result, $list_id)
    {
        $subscriber = ListSubscriber::model()
            ->findByAttributes(array('email' => $result->email, 'list_id' => $list_id));

        return $subscriber ?: new ListSubscriber();
    }

    protected function storeListFieldValues($subscriber, $result)
    {
        foreach ($subscriber->list->fields as $field) {
            $list_field_value = new ListFieldValue();
            $list_field_value->field_id = $field->field_id;
            $list_field_value->subscriber_id = $subscriber->subscriber_id;
            
            if ($field->label === 'Email') {
                $list_field_value->value = $subscriber->email;
            } elseif ($field->label === 'Coupon') {
                $list_field_value->value = $result->promo_code;
            } elseif ($field->label === 'Days since trial began') {
                $list_field_value->value = '';
            } elseif ($field->label === 'WHMCS status') {
                $list_field_value->value = $result->domainstatus;
            } else {
                $list_field_value->value = '';
            }

            $list_field_value->save();
        }
    }
}
