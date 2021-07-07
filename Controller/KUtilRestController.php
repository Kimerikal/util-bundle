<?php

namespace Kimerikal\UtilBundle\Controller;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Kimerikal\UtilBundle\Repository\KPaginator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

class KUtilRestController extends UtilController
{
    const ERROR_DEFAULT_MSG = 'Ocurrió un error inesperado. Comprueba tu conexión a internet y vuelve a intentarlo más tarde.';
    const DEFAULT_MAIL = 'mailer_user';

    /**
     * @Route("/api/autogen/list/{entityClass}/{limit}/{offset}", name="k_util_api_autogen_list", methods={"GET"})
     * @return Response
     */
    public function list(Request $r, $entityClass, $limit, $offset)
    {
        return $this->mList($this->getEntityUrlMap($entityClass), $limit, $offset);
    }

    /**
     * @Route("/api/autogen/detail/{entityClass}/{id}", name="k_util_api_autogen_detail")
     * @param $entityClass
     * @param $id
     * @return JsonResponse
     * @throws \Exception
     */
    public function detail($entityClass, $id)
    {
        if (empty($id))
            return new JsonResponse(['done' => false, 'msg' => 'Object ID is required'], Response::HTTP_BAD_REQUEST);

        $object = $this->doctrineRepo($this->getEntityUrlMap($entityClass))->find($id);
        if (!$object)
            return new JsonResponse(['done' => false, 'msg' => 'Object not found.'], Response::HTTP_BAD_REQUEST);

        return $this->mDetail($object);
    }

    /**
     * @Route("/api/autogen/save/{entityClass}", name="k_util_api_autogen_save", methods={"POST"})
     * @return Response
     */
    public function save(Request $r, $entityClass)
    {
        $params = $this->getDefaultResponse();
        $status = Response::HTTP_BAD_REQUEST;

        try {
            $entity = $this->getDoctrine()->getManager()->getRepository($this->getEntityUrlMap($entityClass))->save($r->request->all(), $r->files->all(), $this->get('validator'));
            $this->responseOkDetail($params, $status);
            $params['data'] = $entity;
        } catch (\Exception $e) {
            return $this->returnResponseException($e, $params, $status);
        }

        return new JsonResponse($params, $status);
    }

    protected function getDefaultResponse()
    {
        return ['done' => false, 'msg' => self::ERROR_DEFAULT_MSG];
    }

    protected function responseOkListPaginator(&$params, &$status, KPaginator $paginator)
    {
        $params['done'] = true;
        $params['data'] = [
            'list' => $paginator->getList(),
            'meta' => ['total' => $paginator->getTotal(), 'limit' => $paginator->getLimit(), 'offset' => $paginator->getOffset(), 'remaining' => $paginator->getRemaining()]];
        $params['msg'] = 'Ok';
        $status = Response::HTTP_OK;
    }

    protected function responseOkList(&$params, &$status, $total, $limit, $offset, $list = [])
    {
        if ($list instanceof Paginator) {
            $tmp = $list->getIterator()->getArrayCopy();
            $list = $tmp;
            unset($tmp);
        } else if ($list instanceof KPaginator) {
            $tmp = $list->getResult();
            $list = $tmp;
            unset($tmp);
        }
        $params['done'] = true;
        $params['data'] = ['list' => $list, 'meta' => ['total' => (int)$total, 'limit' => (int)$limit, 'offset' => (int)$offset, 'remaining' => max(0, ($total - $offset - $limit))]];
        $params['msg'] = 'Ok';
        $status = Response::HTTP_OK;
    }

    protected function responseOkDetail(&$params, &$status, $data = null)
    {
        $params['done'] = true;
        $params['data'] = $data;
        $params['msg'] = 'Ok';
        $status = Response::HTTP_OK;
    }

    protected function responseException(\Exception $e, &$params, &$status)
    {
        $status = Response::HTTP_INTERNAL_SERVER_ERROR;
        $params['done'] = false;
        $params['msg'] = $this->filterExceptionMessage($e->getMessage());
        if (array_key_exists($e->getCode(), Response::$statusTexts))
            $status = $e->getCode();
    }

    protected function filterExceptionMessage($msg)
    {
        if (stripos($msg, 'SQLSTATE[23000]:')) {
            $tmp = explode('SQLSTATE[23000]:', $msg);
            return trim($tmp[1]);
        } else if (stripos($msg, 'SQLSTATE[HY000]:')) {
            $tmp = explode('SQLSTATE[HY000]:', $msg);
            return trim($tmp[1]);
        }

        return $msg;
    }

    protected function returnResponseException(\Exception $e, &$params, &$status)
    {
        $this->responseException($e, $params, $status);
        return new JsonResponse($params, $status);
    }

    protected function mList($className, $limit = 50, $offset = 0)
    {
        $status = Response::HTTP_INTERNAL_SERVER_ERROR;
        $params = $this->getDefaultResponse();
        try {
            $list = $this->_repo($className)->loadAll($offset, $limit, true);
            if ($list instanceof KPaginator)
                $this->responseOkListPaginator($params, $status, $list);
            else {
                $count = $list instanceof Paginator ? $list->count() : count($list);
                $this->responseOkList($params, $status, $count, $limit, $offset, $list);
            }
        } catch (\Exception $e) {
            $this->responseException($e, $params, $status);
        }

        return new JsonResponse($params, $status);
    }

    public function mDetail($object)
    {
        $status = Response::HTTP_INTERNAL_SERVER_ERROR;
        $params = $this->getDefaultResponse();
        try {
            if (method_exists($object, 'setHits')) {
                $object->setHits(($object->getHits() + 1));
                $this->_em()->persist($object);
                $this->_em()->flush();
            }

            $this->responseOkDetail($params, $status);
            $params['data'] = $object;
        } catch (\Exception $e) {
            $this->responseException($e, $params, $status);
        }

        return new JsonResponse($params, $status);
    }

    protected function getUserFromToken(Request $r = null)
    {
        $token = $this->get('security.token_storage')->getToken();
        if (empty($token) && !empty($r)) {
            if (!$user && $r->headers->has('authorization')) {
                $tmp = $r->headers->get('authorization');
                if (!empty($tmp)) {
                    $tmp = str_replace('Bearer ', '', $token);
                    $token = $this->_repo('KUserBundle:AccessToken')->findOneBy(['token' => $token]);
                    if (empty($token) || !method_exists($token, 'getUser'))
                        return null;
                }
            }
        }

        return $token->getUser();
    }
}