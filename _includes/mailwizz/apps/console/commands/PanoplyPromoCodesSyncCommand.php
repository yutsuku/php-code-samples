<?php defined('MW_PATH') || exit('No direct script access allowed');

require_once('PanoplyCustomersCommand.php');

class PanoplyPromoCodesSyncCommand extends PanoplyCustomersCommand
{
    protected $list_name = 'Trial Customers';
    
    protected function store($result)
    {
        $this->updateTrialDays();
        parent::store($result);
    }
    
    protected function updateTrialDays()
    {
        $panoply_list = new PanoplyList($this);
        $trial_list = $panoply_list->getByName($this->list_name);
        
        foreach ($trial_list->subscribers as $subscriber) {
            foreach ($subscriber->fieldValues as $field) {
                $reg_datetime = $subscriber->date_added;
                if ($field->field->label === 'Days since trial began') {
                    $d1 = new DateTime("now");
                    $d2 = new DateTime($reg_datetime);
                    $interval = $d1->diff($d2);
                    $field->value = $interval->format('%a');
                    $field->save();
                }
            }
        }
    }
    
    protected function getQuery()
    {
        return "SELECT
                    tblclients.email, 
                    tblclients.ip AS ip_address, 
                    tblclients.updated_at as last_updated, 
                    TIMESTAMP(tblhosting.regdate) as date_added,
                    'import' AS source, 
                    'confirmed' AS status, 
                    tblpromotions.code AS promo_code,
                    tblhosting.domainstatus
                FROM tblclients
                INNER JOIN tblhosting
                    ON tblclients.id = tblhosting.userid
                INNER JOIN tblpromotions
                    ON tblhosting.promoid = tblpromotions.id
                WHERE tblhosting.promoid > 0
                    AND tblclients.updated_at > (NOW() - interval 2 day)
                    AND tblpromotions.code IN ('2cents', '2centswht', '1dollartrial')
                ORDER BY tblclients.updated_at DESC;";
    }
}
