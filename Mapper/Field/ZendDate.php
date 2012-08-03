<?PHP

class SwaleORM_Mapper_Field_ZendDate {

    public function fromDb($field) {
        return new Zend_Date($field, ISO_8601);
    }

    public function toDb($field) {
        return $field->toString('YYYY-MM-dd HH:mm:ss');
    }
}
