import React, { useState } from 'react';
import axios from 'axios';

function ForgotPassword() {
    const [email, setEmail] = useState('');
    const [status, setStatus] = useState('');
    const [errors, setErrors] = useState({});

    const handleChange = (e) => {
        setEmail(e.target.value);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            const response = await axios.post('/password-reset', { email });
            setStatus(response.data.status);
            setErrors({});
        } catch (error) {
            if (error.response && error.response.data.errors) {
                setErrors(error.response.data.errors);
            }
        }
    };

    return (
        <div className="bg-navyblue wrapper">
            <div className="container">
                <div className="row justify-content-center">
                    <div className="col-md-4 mt-5 mx-auto login_container">
                        <h1 className="h3 mb-3 font-weight-normal text-uppercase text-center mb-5">
                            <span className="title color-white">Forgot Password<hr className="centered-hr" /></span>
                        </h1>

                        {status && (
                            <div className="alert alert-success" role="alert">
                                {status}
                            </div>
                        )}

                        <form onSubmit={handleSubmit}>
        
                            <div className="form-group position-relative" id="register_container">
                                <span className={"input_bg_image span_email"}></span>
                                <input
                                    type="email"
                                    className="form-control"
                                    name="email"
                                    placeholder="Email"
                                    value={email}
                                    onChange={handleChange}
                                    id="email"
                                />
                                {errors && errors.email ? <label className="error" htmlFor="email">{errors.email}</label> : ""}
                            </div>

                            <div className="text-center mt-5">
                                <button type="submit" className="reset-password-btn">
                                    <img src='/images/btn_next.png' width="50" alt="Next" />
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default ForgotPassword;