<?PHP

abstract class SwaleORM_Mapper_Field_Abstract {

    public abstract function fromDB($field);
    public abstract function toDb($field);

}
