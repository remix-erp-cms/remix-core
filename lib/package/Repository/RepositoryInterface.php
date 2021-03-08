<?php namespace Package\Repository;

interface RepositoryInterface {
    /**
     * @param $pagin
     * @param $filter
     * @return mixed
     */
    public function all($pagin, $filter);

    /**
     * @param $id
     * @return mixed
     */
    public function find($id);

    /**
     * @param $data
     * @return mixed
     */
    public function create($data);

    /**
     * @param $data
     * @return mixed
     */
    public function update($data);

    /**
     * @param $data
     * @param $array_id
     * @return mixed
     */
    public function createManyOfRow($data);

    /**
     * @param $id
     * @return mixed
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
     * @param $file
     * @param $data
     * @param $author
     * @return mixed
     */
    public function uploadAndSaveFile($file, $data, $author);

    /**
     * @return mixed
     */
    public function withErrors();
}
