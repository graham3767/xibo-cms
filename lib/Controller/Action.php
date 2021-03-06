<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Xibo\Controller;

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Slim\Views\Twig;
use Xibo\Entity\User;
use Xibo\Entity\Widget;
use Xibo\Factory\ActionFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\HelpServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class Action
 * @package Xibo\Controller
 */
class Action  extends Base
{

    /**
     * @var ActionFactory
     */
    private $actionFactory;

    /** @var LayoutFactory */
    private $layoutFactory;

    /** @var RegionFactory */
    private $regionFactory;

    /** @var WidgetFactory */
    private $widgetFactory;

    /** @var ModuleFactory */
    private $moduleFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param ApplicationState $state
     * @param User $user
     * @param HelpServiceInterface $help
     * @param ConfigServiceInterface $config
     * @param ActionFactory $actionFactory
     * @param LayoutFactory $layoutFactory
     * @param RegionFactory $regionFactory
     * @param WidgetFactory $widgetFactory
     * @param ModuleFactory $moduleFactory
     * @param Twig $view
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $config, $actionFactory, $layoutFactory, $regionFactory, $widgetFactory, $moduleFactory, Twig $view)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $config, $view);

        $this->actionFactory = $actionFactory;
        $this->layoutFactory = $layoutFactory;
        $this->regionFactory = $regionFactory;
        $this->widgetFactory = $widgetFactory;
        $this->moduleFactory = $moduleFactory;
    }


    /**
     * Returns a Grid of Actions
     *
     * @SWG\Get(
     *  path="/action",
     *  operationId="actionSearch",
     *  tags={"action"},
     *  summary="Search Actions",
     *  description="Search all Actions this user has access to",
     *  @SWG\Parameter(
     *      name="actionId",
     *      in="query",
     *      description="Filter by Action Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ownerId",
     *      in="query",
     *      description="Filter by Owner Id",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="triggerType",
     *      in="query",
     *      description="Filter by Action trigger type",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="triggerCode",
     *      in="query",
     *      description="Filter by Action trigger code",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="actionType",
     *      in="query",
     *      description="Filter by Action type",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="source",
     *      in="query",
     *      description="Filter by Action source",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="sourceId",
     *      in="query",
     *      description="Filter by Action source Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="target",
     *      in="query",
     *      description="Filter by Action target",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="targetId",
     *      in="query",
     *      description="Filter by Action target Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/Action")
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     */
    public function grid(Request $request, Response $response) : Response
    {
        $parsedParams = $this->getSanitizer($request->getQueryParams());

        $filter = [
            'actionId' => $parsedParams->getInt('actionId'),
            'ownerId' => $parsedParams->getInt('ownerId'),
            'triggerType' => $parsedParams->getString('triggerType'),
            'triggerCode' => $parsedParams->getString('triggerCode'),
            'actionType' => $parsedParams->getString('actionType'),
            'source' => $parsedParams->getString('source'),
            'sourceId' => $parsedParams->getInt('sourceId'),
            'target' => $parsedParams->getString('target'),
            'targetId' => $parsedParams->getInt('targetId'),
            'widgetId' => $parsedParams->getInt('widgetId'),
            'layoutCode' => $parsedParams->getString('layoutCode')
        ];

        $actions = $this->actionFactory->query($this->gridRenderSort($request), $this->gridRenderFilter($filter, $request));

        foreach ($actions as $action) {
            $action->widgetName = null;
            $action->regionName = null;

            if ($action->actionType === 'navWidget' && $action->widgetId != null) {
                $widget = $this->widgetFactory->loadByWidgetId($action->widgetId);
                $module = $this->moduleFactory->createWithWidget($widget);

                // dynamic field to display in the grid instead of widgetId
                $action->widgetName = $module->getName();
            }

            if ($action->target === 'region' && $action->targetId != null) {
                $region = $this->regionFactory->getById($action->targetId);

                // dynamic field to display in the grid instead of regionId
                $action->regionName = $region->name;
            }

            if ($this->isApi($request)) {
                continue;
            }

            $action->includeProperty('buttons');
            $action->buttons = [];

            $action->buttons[] = [
                'id' => 'action_edit_button',
                'url' => $this->urlFor($request,'action.edit.form', ['id' => $action->actionId]),
                'text' => __('Edit')
            ];

            $action->buttons[] = [
                'id' => 'action_delete_button',
                'url' => $this->urlFor($request,'action.delete.form', ['id' => $action->actionId]),
                'text' => __('Delete')
            ];
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->actionFactory->countLast();
        $this->getState()->setData($actions);

        return $this->render($request, $response);
    }

    /**
     * Action Add Form
     * @param Request $request
     * @param Response $response
     * @param string $source
     * @param int $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     */
    public function addForm(Request $request, Response $response, string $source, int $id) : Response
    {
        $sourceObject = $this->checkIfSourceExists($source, $id);

        if ($source === 'layout') {
            /** @var \Xibo\Entity\Layout $layout */
            $layout = $sourceObject;
        } elseif ($source === 'region') {
            /** @var \Xibo\Entity\Region $region */
            $region = $sourceObject;
            $layout = $this->layoutFactory->getById($region->layoutId);
        } else {
            /** @var Widget $widget */
            $widget = $sourceObject;
            $region = $this->regionFactory->getByPlaylistId($widget->playlistId)[0];
            $layout = $this->layoutFactory->getById($region->layoutId);
        }

        // Make sure the Layout is checked out to begin with
        if (!$layout->isEditable()) {
            throw new InvalidArgumentException(__('Layout is not checked out'), 'publishedStatusId');
        }

        $layout->load();

        // all widgets
        $widgets = $layout->getDrawerWidgets();

        foreach ($widgets as $widget) {
            $module = $this->moduleFactory->createWithWidget($widget);
            // if we don't have a name set in the Widget
            $widget->name = sprintf('%s [%s]', $module->getName(), $module->getModuleType());
        }

        $this->getState()->template = 'action-form-add';
        $this->getState()->setData([
            'sourceObject' => $sourceObject,
            'source' => $source,
            'id' => $id,
            'regions' => $layout->regions,
            'widgets' => $widgets,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Add a new Action
     *
     * @SWG\Post(
     *  path="/action/{source}/{sourceId}",
     *  operationId="actionAdd",
     *  tags={"action"},
     *  summary="Add Action",
     *  description="Add a new Action",
     *  @SWG\Parameter(
     *      name="source",
     *      in="path",
     *      description="Source for this action layout, region or widget",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="sourceId",
     *      in="path",
     *      description="The id of the source object, layoutId, regionId or widgetId",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="triggerType",
     *      in="formData",
     *      description="Action trigger type, touch or webhook",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="triggerCode",
     *      in="formData",
     *      description="Action trigger code",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="actionType",
     *      in="formData",
     *      description="Action type, next, previous, navLayout, navWidget",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="target",
     *      in="formData",
     *      description="Target for this action, screen or region",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="targetId",
     *      in="formData",
     *      description="The id of the target for this action - regionId if the target is set to region",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="formData",
     *      description="For navWidget actionType, the WidgetId to navigate to",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="layoutCode",
     *      in="formData",
     *      description="For navLayout, the Layout Code identifier to navigate to",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Action"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @param string $source
     * @param int $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     */
    public function add(Request $request, Response $response, string $source, int $id) : Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $triggerType = $sanitizedParams->getString('triggerType');
        $triggerCode = $sanitizedParams->getString('triggerCode', ['defaultOnEmptyString' => true]);
        $actionType = $sanitizedParams->getString('actionType');
        $target = $sanitizedParams->getString('target');
        $targetId = $sanitizedParams->getInt('targetId');
        $widgetId = $sanitizedParams->getInt('widgetId');
        $layoutCode = $sanitizedParams->getString('layoutCode');

        // this will return Layout|Region|Widget object or throw an exception if provided source and sourceId does not exist.
        $sourceObject = $this->checkIfSourceExists($source, $id);

        if ($source == 'layout') {
            /** @var \Xibo\Entity\Layout $layout */
            $layout = $sourceObject;
        } elseif ($source == 'region') {
            /** @var \Xibo\Entity\Region $region */
            $region = $sourceObject;
            $layout = $this->layoutFactory->getById($region->layoutId);
        } else {
            /** @var Widget $widget */
            $widget = $sourceObject;
            $region = $this->regionFactory->getByPlaylistId($widget->playlistId)[0];
            $layout = $this->layoutFactory->getById($region->layoutId);
        }

        // Make sure the Layout is checked out to begin with
        if (!$layout->isEditable()) {
            throw new InvalidArgumentException(__('Layout is not checked out'), 'statusId');
        }

        // restrict to one touch Action per source
        if ($this->actionFactory->checkIfActionExist($source, $id, $triggerType)) {
            throw new InvalidArgumentException(__('Action with specified Trigger Type already exists'), 'triggerType');
        }

        $action = $this->actionFactory->create($triggerType, $triggerCode, $actionType, $source, $id, $target, $targetId, $widgetId, $layoutCode);
        $action->save(['notifyLayout' => true, 'layoutId' => $layout->layoutId]);

        // Return
        $this->getState()->hydrate([
            'message' => __('Added Action'),
            'httpStatus' => 201,
            'id' => $action->actionId,
            'data' => $action,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Campaign Edit Form
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     */
    public function editForm(Request $request, Response $response, int $id) : Response
    {
        $action = $this->actionFactory->getById($id);
        $sourceObject = $this->checkIfSourceExists($action->source, $action->sourceId);

        if ($action->source == 'layout') {
            /** @var \Xibo\Entity\Layout $layout */
            $layout = $sourceObject;
        } elseif ($action->source == 'region') {
            /** @var \Xibo\Entity\Region $region */
            $region = $sourceObject;
            $layout = $this->layoutFactory->getById($region->layoutId);
        } else {
            /** @var Widget $widget */
            $widget = $sourceObject;
            $region = $this->regionFactory->getByPlaylistId($widget->playlistId)[0];
            $layout = $this->layoutFactory->getById($region->layoutId);
        }

        $layout->load();

        // all widgets, assigned to this layout or drawer
        $widgets = $layout->getDrawerWidgets();

        foreach ($widgets as $widget) {
            $module = $this->moduleFactory->createWithWidget($widget);
            // if we don't have a name set in the Widget
            $widget->name = $module->getName();
        }

        // Make sure the Layout is checked out to begin with
        if (!$layout->isEditable()) {
            throw new InvalidArgumentException(__('Layout is not checked out'), 'statusId');
        }

        try {
            $code = (($action->layoutCode != null) ? [$this->layoutFactory->getByCode($action->layoutCode)] : []);
        } catch (NotFoundException $notFoundException) {
            $code = [];
        }

        $this->getState()->template = 'action-form-edit';
        $this->getState()->setData([
            'help' => $this->getHelp()->link('Action', 'Edit'),
            'action' => $action,
            'source' => $action->source,
            'regions' => $layout->regions,
            'widgets' => $widgets,
            'layout' => $code
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit Action
     *
     * @SWG\PUT(
     *  path="/action/{actionId}",
     *  operationId="actionAdd",
     *  tags={"action"},
     *  summary="Add Action",
     *  description="Add a new Action",
     *  @SWG\Parameter(
     *      name="actionId",
     *      in="path",
     *      description="Action ID to edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="triggerType",
     *      in="formData",
     *      description="Action trigger type, touch, webhook",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="triggerCode",
     *      in="formData",
     *      description="Action trigger code",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="actionType",
     *      in="formData",
     *      description="Action type, next, previous, navLayout, navWidget",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="target",
     *      in="formData",
     *      description="Target for this action, screen or region",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="targetId",
     *      in="formData",
     *      description="The id of the target for this action, regionId if target set to region",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="formData",
     *      description="For navWidget actionType, the WidgetId to navigate to",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="layoutCode",
     *      in="formData",
     *      description="For navLayout, the Layout Code identifier to navigate to",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Action"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return Response
     * @throws GeneralException
     */
    public function edit(Request $request, Response $response, int $id) : Response
    {
        $action = $this->actionFactory->getById($id);

        $sanitizedParams = $this->getSanitizer($request->getParams());

        $sourceObject = $this->checkIfSourceExists($action->source, $action->sourceId);

        if ($action->source == 'layout') {
            /** @var \Xibo\Entity\Layout $layout */
            $layout = $sourceObject;
        } elseif ($action->source == 'region') {
            /** @var \Xibo\Entity\Region $region */
            $region = $sourceObject;
            $layout = $this->layoutFactory->getById($region->layoutId);
        } else {
            /** @var Widget $widget */
            $widget = $sourceObject;
            $region = $this->regionFactory->getByPlaylistId($widget->playlistId)[0];
            $layout = $this->layoutFactory->getById($region->layoutId);
        }

        // Make sure the Layout is checked out to begin with
        if (!$layout->isEditable()) {
            throw new InvalidArgumentException(__('Layout is not checked out'), 'statusId');
        }

        // restrict to one touch Action per source
        if ($this->actionFactory->checkIfActionExist($action->source, $action->sourceId, $action->triggerType, $action->actionId)) {
            throw new InvalidArgumentException(__('Action with specified Trigger Type already exists'), 'triggerType');
        }

        $action->triggerType = $sanitizedParams->getString('triggerType');
        $action->triggerCode = $sanitizedParams->getString('triggerCode', ['defaultOnEmptyString' => true]);
        $action->actionType = $sanitizedParams->getString('actionType');
        $action->target = $sanitizedParams->getString('target');
        $action->targetId = $sanitizedParams->getInt('targetId');
        $action->widgetId = $sanitizedParams->getInt('widgetId');
        $action->layoutCode = $sanitizedParams->getString('layoutCode');

        $action->save(['notifyLayout' => true, 'layoutId' => $layout->layoutId]);

        // Return
        $this->getState()->hydrate([
            'message' => __('Edited Action'),
            'id' => $action->actionId,
            'data' => $action
        ]);

        return $this->render($request, $response);
    }

    /**
     * Shows the Delete Group Form
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     */
    function deleteForm(Request $request, Response $response, int $id) : Response
    {
        $action = $this->actionFactory->getById($id);

        $sourceObject = $this->checkIfSourceExists($action->source, $action->sourceId);

        if ($action->source == 'layout') {
            /** @var \Xibo\Entity\Layout $layout */
            $layout = $sourceObject;
        } elseif ($action->source == 'region') {
            /** @var \Xibo\Entity\Region $region */
            $region = $sourceObject;
            $layout = $this->layoutFactory->getById($region->layoutId);
        } else {
            /** @var Widget $widget */
            $widget = $sourceObject;
            $region = $this->regionFactory->getByPlaylistId($widget->playlistId)[0];
            $layout = $this->layoutFactory->getById($region->layoutId);
        }

        // Make sure the Layout is checked out to begin with
        if (!$layout->isEditable()) {
            throw new InvalidArgumentException(__('Layout is not checked out'), 'publishedStatusId');
        }

        $this->getState()->template = 'action-form-delete';
        $this->getState()->setData([
            'action' => $action,
            'source' => $action->source,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete Action
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     *
     * @SWG\Delete(
     *  path="/action/{actionId}",
     *  operationId="actionDelete",
     *  tags={"action"},
     *  summary="Delete Action",
     *  description="Delete an existing Action",
     *  @SWG\Parameter(
     *      name="actionId",
     *      in="path",
     *      description="The Action ID to Delete",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function delete(Request $request, Response $response, int $id) : Response
    {
        $action = $this->actionFactory->getById($id);
        $sourceObject = $this->checkIfSourceExists($action->source, $action->sourceId);

        if ($action->source == 'layout') {
            /** @var \Xibo\Entity\Layout $layout */
            $layout = $sourceObject;
        } elseif ($action->source == 'region') {
            /** @var \Xibo\Entity\Region $region */
            $region = $sourceObject;
            $layout = $this->layoutFactory->getById($region->layoutId);
        } else {
            /** @var Widget $widget */
            $widget = $sourceObject;
            $region = $this->regionFactory->getByPlaylistId($widget->playlistId)[0];
            $layout = $this->layoutFactory->getById($region->layoutId);
        }

        // Make sure the Layout is checked out to begin with
        if (!$layout->isEditable()) {
            throw new InvalidArgumentException(__('Layout is not checked out'), 'statusId');
        }

        $action->notifyLayout($layout->layoutId);
        $action->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted Action'))
        ]);

        return $this->render($request, $response);

    }

    /**
     * @param string $source
     * @param int $sourceId
     * @return \Xibo\Entity\Layout|\Xibo\Entity\Region|\Xibo\Entity\Widget
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function checkIfSourceExists(string $source, int $sourceId)
    {
        if (strtolower($source) === 'layout') {
            $object = $this->layoutFactory->getById($sourceId);
        } elseif (strtolower($source) === 'region') {
            $object = $this->regionFactory->getById($sourceId);
        } elseif (strtolower($source) === 'widget') {
            $object = $this->widgetFactory->getById($sourceId);
        } else {
            throw new InvalidArgumentException(__('Provided source is invalid. ') , 'source');
        }

        return $object;
    }
}