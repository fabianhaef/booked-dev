<?php

namespace fabian\booked\controllers\cp;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use fabian\booked\Booked;
use fabian\booked\elements\Availability;
use fabian\booked\models\EventDate;
use fabian\booked\services\AvailabilityService;
use yii\web\NotFoundHttpException;

/**
 * CP Availability Controller - Handles Control Panel availability management
 */
class AvailabilityController extends Controller
{
    private AvailabilityService $availabilityService;

    public function init(): void
    {
        parent::init();
        $this->availabilityService = Booked::getInstance()->availability;
    }

    /**
     * Availability index page
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('booking/availability/_index', [
            'title' => 'Verfügbarkeit',
        ]);
    }

    /**
     * Edit availability
     */
    public function actionEdit(int $id = null): Response
    {
        if ($id) {
            $availability = Availability::find()->id($id)->one();
            if (!$availability) {
                throw new NotFoundHttpException('Availability not found');
            }
        } else {
            $availability = new Availability();
        }

        return $this->renderTemplate('booking/availability/edit', [
            'availability' => $availability,
            'days' => Availability::getDays(),
        ]);
    }

    /**
     * Save availability
     */
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->request;
        $id = $request->getBodyParam('id');

        // Check if this is a new recurring availability with multiple days
        $isNew = !$id;
        $isRecurring = $request->getRequiredBodyParam('availabilityType') === 'recurring';
        $multipleDays = $request->getBodyParam('days', []);

        // If creating new recurring availability with multiple days, create multiple entries
        if ($isNew && $isRecurring && !empty($multipleDays)) {
            return $this->createMultipleAvailabilities($request, $multipleDays);
        }

        // Otherwise, handle single availability (editing or creating with single day)
        if ($id) {
            $availability = Availability::find()->id($id)->one();
            if (!$availability) {
                throw new NotFoundHttpException('Availability not found');
            }
        } else {
            $availability = new Availability();
        }

        $availability->title = $request->getRequiredBodyParam('title');
        $availability->availabilityType = $request->getRequiredBodyParam('availabilityType');
        $availability->isActive = (bool) $request->getBodyParam('isActive', true);
        $availability->sourceType = $request->getRequiredBodyParam('sourceType');
        $availability->description = $request->getBodyParam('description', '');

        // Handle variations
        $variationIds = $request->getBodyParam('variationIds', []);
        if (!empty($variationIds)) {
            $availability->setVariationIds($variationIds);
        } else {
            $availability->setVariationIds([]);
        }

        // Handle recurring availability
        if ($availability->availabilityType === 'recurring') {
            $availability->dayOfWeek = $request->getRequiredBodyParam('dayOfWeek');
            $availability->startTime = $request->getRequiredBodyParam('startTime');
            $availability->endTime = $request->getRequiredBodyParam('endTime');
        } else {
            // Handle event availability with multiple dates
            $eventDatesData = $request->getBodyParam('eventDates', []);
            $eventDates = [];

            foreach ($eventDatesData as $eventDateData) {
                if (!empty($eventDateData['date']) && !empty($eventDateData['startTime']) && !empty($eventDateData['endTime'])) {
                    $eventDate = new EventDate();
                    $eventDate->eventDate = $eventDateData['date'];
                    $eventDate->startTime = $eventDateData['startTime'];
                    $eventDate->endTime = $eventDateData['endTime'];
                    $eventDates[] = $eventDate;
                }
            }

            $availability->setEventDates($eventDates);
        }

        // Handle source based on type
        if ($availability->sourceType === 'section') {
            $availability->sourceHandle = $request->getBodyParam('sourceHandle');
            $availability->sourceId = null;
            // Store section ID for faster queries
            if ($availability->sourceHandle) {
                $section = Craft::$app->sections->getSectionByHandle($availability->sourceHandle);
                if ($section) {
                    $availability->sourceId = $section->id;
                }
            }
        } else {
            // Entry type
            $sourceId = $request->getBodyParam('sourceId');
            // Element select returns array, get first item
            if (is_array($sourceId)) {
                $sourceId = !empty($sourceId) ? $sourceId[0] : null;
            }
            $availability->sourceId = $sourceId ? (int) $sourceId : null;
            $availability->sourceHandle = null;
        }

        if (!Craft::$app->elements->saveElement($availability)) {
            Craft::$app->session->setError('Unable to save availability.');
            return $this->renderTemplate('booking/availability/edit', [
                'availability' => $availability,
                'days' => Availability::getDays(),
            ]);
        }

        Craft::$app->session->setNotice('Availability saved successfully.');
        return $this->redirect('booking/availability');
    }

    /**
     * Create multiple availability entries for selected days
     */
    private function createMultipleAvailabilities($request, array $days): Response
    {
        $title = $request->getRequiredBodyParam('title');
        $startTime = $request->getRequiredBodyParam('startTime');
        $endTime = $request->getRequiredBodyParam('endTime');
        $isActive = (bool) $request->getBodyParam('isActive', true);
        $sourceType = $request->getRequiredBodyParam('sourceType');
        $description = $request->getBodyParam('description', '');
        $variationIds = $request->getBodyParam('variationIds', []);

        // Get source info
        $sourceHandle = null;
        $sourceId = null;
        if ($sourceType === 'section') {
            $sourceHandle = $request->getBodyParam('sourceHandle');
            if ($sourceHandle) {
                $section = Craft::$app->sections->getSectionByHandle($sourceHandle);
                if ($section) {
                    $sourceId = $section->id;
                }
            }
        } else {
            $sourceIdParam = $request->getBodyParam('sourceId');
            if (is_array($sourceIdParam)) {
                $sourceId = !empty($sourceIdParam) ? (int)$sourceIdParam[0] : null;
            } else {
                $sourceId = $sourceIdParam ? (int)$sourceIdParam : null;
            }
        }

        $created = 0;
        $errors = [];
        $dayNames = Availability::getDays();

        foreach ($days as $dayOfWeek) {
            $availability = new Availability();
            $availability->title = $title . ' - ' . $dayNames[$dayOfWeek];
            $availability->availabilityType = 'recurring';
            $availability->dayOfWeek = (int)$dayOfWeek;
            $availability->startTime = $startTime;
            $availability->endTime = $endTime;
            $availability->isActive = $isActive;
            $availability->sourceType = $sourceType;
            $availability->sourceHandle = $sourceHandle;
            $availability->sourceId = $sourceId;
            $availability->description = $description;

            if (!empty($variationIds)) {
                $availability->setVariationIds($variationIds);
            }

            if (Craft::$app->elements->saveElement($availability)) {
                $created++;
            } else {
                $errors[] = "Failed to create availability for {$dayNames[$dayOfWeek]}";
            }
        }

        if ($created > 0) {
            Craft::$app->session->setNotice("Created {$created} availability period" . ($created > 1 ? 's' : '') . ".");
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                Craft::$app->session->setError($error);
            }
        }

        return $this->redirect('booking/availability');
    }

    /**
     * Delete availability
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();

        $id = Craft::$app->request->getRequiredBodyParam('id');
        $availability = Availability::find()->id($id)->one();

        if (!$availability) {
            throw new NotFoundHttpException('Availability not found');
        }

        if (Craft::$app->elements->deleteElement($availability)) {
            Craft::$app->session->setNotice('Availability deleted successfully.');
        } else {
            Craft::$app->session->setError('Unable to delete availability.');
        }

        return $this->redirect('booking/availability');
    }

    /**
     * Toggle availability status
     */
    public function actionToggleStatus(): Response
    {
        $this->requirePostRequest();

        $id = Craft::$app->request->getRequiredBodyParam('id');
        $availability = $this->availabilityService->getAvailabilityById($id);

        if (!$availability) {
            throw new NotFoundHttpException('Availability not found');
        }

        $availability->isActive = !$availability->isActive;

        if ($availability->save()) {
            if (Craft::$app->request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => true,
                    'isActive' => $availability->isActive
                ]);
            } else {
                Craft::$app->session->setNotice('Availability status updated.');
            }
        } else {
            if (Craft::$app->request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'message' => 'Unable to update status'
                ]);
            } else {
                Craft::$app->session->setError('Unable to update status.');
            }
        }

        return $this->redirectToPostedUrl();
    }

}
