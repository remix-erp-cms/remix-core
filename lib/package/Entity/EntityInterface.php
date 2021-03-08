<?php
namespace Package\Entity;

interface EntityInterface {

    /**
     * @param $pagin
     * @return mixed
     */
    public function all($pagin, $filter);

    /**
     * @param $id
     * @return mixed
     */
    public function find($id);

    /**
     * @param array $input
     * @return mixed
     */
    public function create(array $input);

    /**
     * @param array $input
     * @return mixed
     */
    public function createManyOfRow(array $input);

    /**
     * @param array $input
     * @return mixed
     */
    public function update(array $input);

    /**
     * Delete
     *
     * @param int $id
     * @return boolean
     */
    public function delete($id);

    /**
     * @return mixed
     */
    public function allTrash($pagin);

    /**
     * @param $id
     * @return mixed
     */

    public function trash($id, $trash);

    /**
     * @return mixed
     */
    public function errors();

}
