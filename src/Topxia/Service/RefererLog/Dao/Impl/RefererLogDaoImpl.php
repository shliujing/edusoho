<?php
namespace Topxia\Service\RefererLog\Dao\Impl;

use Topxia\Service\Common\BaseDao;
use Topxia\Service\RefererLog\Dao\RefererLogDao;

class RefererLogDaoImpl extends BaseDao implements RefererLogDao
{
    protected $table = 'referer_log';

    public function addRefererLog($referLog)
    {
        $referLog['createdTime'] = time();
        $referLog['updatedTime'] = $referLog['createdTime'];
        $affected                = $this->getConnection()->insert($this->getTable(), $referLog);
        if ($affected <= 0) {
            throw $this->createDaoException('Insert user error.');
        }

        return $this->getRefererLogById($this->getConnection()->lastInsertId());
    }

    public function getRefererLogById($id)
    {
        $that = $this;
        return $this->fetchCached("id:{$id}", $id, function ($id) use ($that) {
            $sql = "SELECT * FROM {$that->getTable()} where id =? LIMIT 1";
            return $that->getConnection()->fetchAssoc($sql, array($id)) ?: null;
        });
    }

    public function searchAnalysisSummary($conditions)
    {
        $orderBy = array('count', 'DESC');
        $groupBy = 'refererName';
        $builder = $this->createQueryBuilder($conditions, $orderBy, $groupBy)
            ->select('refererName, count(targetid) as count, sum(ordercount) as orderCount');
        return $builder->execute()->fetchAll() ?: array();
    }

    public function searchAnalysisSummaryList($conditions, $groupBy, $start, $limit)
    {
        $this->filterStartLimit($start, $limit);
        $orderBy = array('count', 'DESC');
        $builder = $this->createQueryBuilder($conditions, $orderBy, $groupBy)
            ->select("{$groupBy}, count(id) as count , sum(ordercount) as orderCount")
            ->setFirstResult($start)
            ->setMaxResults($limit);

        return $builder->execute()->fetchAll() ?: array();
    }

    public function searchAnalysisSummaryListCount($conditions, $field)
    {
        $builder = $this->createQueryBuilder($conditions)
            ->select("COUNT(DISTINCT {$field})");
        return $builder->execute()->fetchColumn(0);
    }

    public function searchRefererLogCount($conditions)
    {
        $builder = $this->createQueryBuilder($conditions)
            ->select('COUNT(*)')
        ;
        return $builder->execute()->fetchColumn(0);
    }

    public function searchRefererLogs($conditions, $orderBy, $start, $limit)
    {
        $this->filterStartLimit($start, $limit);
        $builder = $this->createQueryBuilder($conditions)
            ->select('*')
            ->orderBy($orderBy[0], $orderBy[1])
            ->setFirstResult($start)
            ->setMaxResults($limit)
        ;
        return $builder->execute()->fetchAll() ?: array();
    }

    protected function createQueryBuilder($conditions, $orderBy = null, $groupBy = null)
    {
        $builder = $this->createDynamicQueryBuilder($conditions)
            ->from($this->getTable(), 'r')
            ->andWhere('targetType = :targetType')
            ->andWhere('targetId = :targetId')
            ->andWhere('targetInnerType = :targetInnerType')
            ->andWhere('createdTime >= :startTime')
            ->andWhere('createdTime <= :endTime');

        for ($i = 0; $i < count($orderBy); $i = $i + 2) {
            $builder->addOrderBy($orderBy[$i], $orderBy[$i + 1]);
        };

        if (!empty($groupBy)) {
            $builder->groupBy($groupBy);
        }
        return $builder;
    }
}
