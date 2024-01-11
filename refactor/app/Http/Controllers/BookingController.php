<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Models\Distance;
use Exception;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{

    protected $repository;
    protected $config;

    /**
     * @param BookingRepository $bookingRepository
     * @param Config $config
     */
    public function __construct(BookingRepository $bookingRepository, Config $config)
    {
        $this->repository = $bookingRepository;
        $this->config = $config;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $isAdmin = $request->__authenticatedUser->user_type == $this->config->get('constants.ADMIN_ROLE_ID');
        $isSuperAdmin = $request->__authenticatedUser->user_type == $this->config->get('constants.SUPERADMIN_ROLE_ID');

        $response = ($user_id = $request->get('user_id')) ? $this->repository->getUsersJobs($user_id) : ($isAdmin || $isSuperAdmin) ? $this->repository->getAll($request) : null;

        return Response::json($response);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        $job = $this->repository->with('translatorJobRel.user')->find($id);
        return Response::json($job);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            // Define your validation rules here
        ]);

        $response = $this->repository->store($request->__authenticatedUser, $validatedData);

        return Response::json($response);
    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, Request $request)
    {
        $data = $request->all();
        $cuser = $request->__authenticatedUser;

        $response = $this->repository->updateJob($id, array_except($data, ['_token', 'submit']), $cuser);

        return Response::json($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function distanceFeed(Request $request)
    {
        $data = $request->all();

        $distance = $data['distance'] ?? "";
        $time = $data['time'] ?? "";
        $jobid = $data['jobid'] ?? "";
        $session = $data['session_time'] ?? "";
        $flagged = $data['flagged'] == 'true' ? 'yes' : 'no';
        $manually_handled = $data['manually_handled'] == 'true' ? 'yes' : 'no';
        $by_admin = $data['by_admin'] == 'true' ? 'yes' : 'no';
        $admincomment = $data['admincomment'] ?? "";

        if ($time || $distance) {
            Distance::where('job_id', $jobid)->update(['distance' => $distance, 'time' => $time]);
        }

        if ($admincomment || $session || $flagged || $manually_handled || $by_admin) {
            Job::where('id', $jobid)->update(['admin_comments' => $admincomment, 'flagged' => $flagged, 'session_time' => $session, 'manually_handled' => $manually_handled, 'by_admin' => $by_admin]);
        }

        return Response::json('Record updated!');
    }


    /**
     * @param Request $request
     * @return mixed
     */
    public function reopen(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->reopen($data);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function resendNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);
        $job_data = $this->repository->jobToData($job);
        $this->repository->sendNotificationTranslator($job, $job_data, '*');
        return response(['success' => 'Push sent']);
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return ResponseFactory|Response
     */
    public function resendSMSNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);
        $job_data = $this->repository->jobToData($job);

        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return response(['success' => 'SMS sent']);
        } catch (Exception $e) {
            return response(['success' => $e->getMessage()]);
        }
    }

}
