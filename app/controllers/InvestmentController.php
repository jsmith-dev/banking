<?php

class InvestmentController extends \UserBaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		$accounts = $this->user->getInvestments();
                View::share('accounts', $accounts);
                $this->layout->content = View::make('investment.list');
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{
                $from = array();
                foreach($this->user->getAccounts() as $account) {
                    if($account->getType() == Account::CHECKING || $account->getType() == Account::SAVING) {
                        $from[$account->getId()] = $account->getAccountNo()."(".$account->getBalance()." CAD)";
                    }
                }
                View::share('from', $from);
            
		$this->layout->content = View::make('investment.create');
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store()
	{
		// validate
		// read more on validation at http://laravel.com/docs/validation
		$rules = array(
			'account'       => 'required',
			'term'      => 'required',
			'amount'        => 'required|numeric|min:20'
		);
		$validator = Validator::make(Input::all(), $rules);

		// process the login
		if ($validator->fails()) {
			return Redirect::to('investment/create')
				->withErrors($validator);
		} else {
			// store
                        $fromAccount = Doctrine::getRepository("Account")->find(Input::get('account'));
			
                        if($fromAccount->getBalance() < Input::get('amount')) {
                            return Redirect::to('investment/create')
				->withErrors("You don't have enough balance");
                        }
                        
                        $transaction = new Transaction();
			$transaction->setAccount($fromAccount);
			$transaction->setAmount(Input::get('amount'));
                        $transaction->setDescription("Investment");
			$transaction->setType(Transaction::DEBIT);
                        Doctrine::persist($transaction);
                        
                        $investment = new Investment();
                        
                        $investment->setUser($this->user);
                        $investment->setTermLength((Input::get('term') == Investment::SIX_MONTH)?6:12); 
                        $investment->setIsActive(true);
                        $investment->setTermType(Investment::FIXED);
                        $investment->setInterestRate((Input::get('term') == Investment::SIX_MONTH)?1:2);
                        $investment->setAmount(Input::get('amount'));
                        Doctrine::persist($investment);
                        
                        
                        Doctrine::flush();
                        
			// redirect
			Session::flash('message', 'Investment Account created Successfully!');
			return Redirect::to('investment');
		}
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id)
	{
            $investment = Doctrine::getRepository("Investment")->find($id);
            View::share('account', $investment);
            $accounts = $this->user->getAccounts();
            $to = array(0 => "Select An Account");

            foreach($accounts as $account) {
                if($account->getIsActive() && $account->getType() == Account::CHECKING) {
                    $to[$account->getId()] = $account->getAccountNo()."(".$account->getBalance().")";
                }
            }
            View::share('to', $to);
            
            $this->layout->content = View::make('investment.show');
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($id)
	{
		//
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id)
	{
             /**
             * @var Investment Description
             */
            $investment = Doctrine::getRepository("Investment")->find($id);
            
            $rules = array(
			'to_account'       => 'required',
			'amount'        => 'required|numeric|max:'.$investment->getAmount()
		);
            $validator = Validator::make(Input::all(), $rules);

            // process the login
            if ($validator->fails()) {
                    return Redirect::to('investment/'.$id)
                            ->withErrors($validator);
            }
            
            $amount = Input::get('amount');
           
            $toAccount = Doctrine::getRepository("Account")->find(Input::get('to_account'));
            $interest = 0;
            if($investment->isMatured()) {
                $interest = $investment->getInterestTotal($amount);
                $transaction1 = new Transaction();
                $transaction1->setAccount($toAccount);
                $transaction1->setAmount($interest);
                $transaction1->setDescription("Interest Credit From Investment");
                $transaction1->setType(Transaction::CREDIT);
                Doctrine::persist($transaction1);
            }
            
            $transaction = new Transaction();
            $transaction->setAccount($toAccount);
            $transaction->setAmount($amount);
            $transaction->setDescription("Redeem From Investment");
            $transaction->setType(Transaction::CREDIT);
            Doctrine::persist($transaction);
            
            if($amount == $investment->getAmount()) {
                $investment->setIsActive(false);
            }
            else {
                $balance = $investment->getAmount() - $amount;
                $investment->setAmount($balance);
            }
            Doctrine::persist($investment);
            
            Doctrine::flush();
            
            return Redirect::to('investment');
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id)
	{
		//
	}

}