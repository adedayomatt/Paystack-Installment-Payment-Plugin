let getInstallment = () => {
  let select = document.getElementById('installment-month');
  return {
    month: select.options[select.selectedIndex].value,
    total: select.getAttribute('total')
  }
}

let installmentMonthChanged = () => {
  let installment = getInstallment();
  if(installment.month){
    document.getElementById('installment-details').innerHTML = `
      You are paying the total sum of NGN${installment.total.toLocaleString()} in ${installment.month} installment(s). You will be paying NGN${Math.ceil(installment.total/installment.month).toLocaleString()} now.
    `;
  }else{
    document.getElementById('installment-details').innerHTML = `Select desired number of installments above`;
  }
}

let createPlan = () => {
  return new Promise((resolve, reject) => {
    let installment = getInstallment();
    let month = installment.month;

    if(!month){
      reject("Select installment month");
    }else{
      jQuery.ajax({
        url: `https://api.paystack.co/plan`,
        type: "POST",
        data: {
          name: params.subscription_name,
          interval: params.interval,
          amount: params.total_amount / month,
          invoice_limit: month,
          description: params.description
        },
        // dataType: 'application/json',
        headers: {
          Authorization: `Bearer ${params.secret_key}`,
        }
      })
      .success(function(response, textStatus, jqXHR){
        resolve(response);
      })
      .fail(function(jqXHR, textStatus, errorThrown){
        reject("Failed to create installment plan")
      })
      .always(() => {
        // console.log('success/fail');
      })
    }
  });
}

let makePayment = (plan) => {
    return new Promise((resolve, reject) => {
        if(plan){
            let handler = PaystackPop.setup({
                key: params.public_key,
                email: params.email,
                currency: 'NGN',
                firstname: params.first_name,
                lastname: params.last_name,
                reference: params.reference,
                plan: plan,
                channels: ['card'],
                callback: function(response) {
                    document.getElementById('initial-payment-payload').value = JSON.stringify(response);
                    resolve( 'Payment Successfull' );
                },
                onClose: function() {
                    reject('Transaction was not completed, window closed.');
                },
            });
            handler.openIframe();
        }else{
            reject('No valid installment plan')
        }
    })

}

jQuery(function($){

      let checkout_form = $( 'form.woocommerce-checkout' );
      checkout_form.on( 'checkout_place_order', function(e){

        e.preventDefault();

        if(!params.user_approved){
          alert("Your account has not been approved to pay on installment yet.")
          return false;
        }

        let subscriptionPlan = document.getElementById('subscription-payload').value;
        let initialPayment = document.getElementById('initial-payment-payload').value;

        // Ensure the subscription plan and initial payment are being made before finally submitting
        if(subscriptionPlan !== '' && initialPayment !== ''){
          return true;
        }
        if(subscriptionPlan !== ''){
          let plan = JSON.parse(subscriptionPlan);
          makePayment(plan.plan_code).then(response => {
              alert(response);
              checkout_form.submit();
          }).catch(e => {
              alert(e);
          })
        }
        else{
          createPlan().then(response => {
            if(response.data.plan_code){
              document.getElementById('subscription-payload').value = JSON.stringify(response.data);
              return makePayment(response.data.plan_code);
            }
          }).then(response => {
              alert(response);
              checkout_form.submit();
            })
          .catch(e => {
            alert(e);
          })
        }

      return false;
    });
});
