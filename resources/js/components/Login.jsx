import React from "react";
import ReactDOM from "react-dom";
import Logo from "./Logo";
import LoginForm from "./LoginForm";

function Login() {
    console.log("Login component is being rendered");
    return (
        <div className ="bg-navyblue wrapper">
            <div className ="container">
                <div className ="row justify-content-center">
                    <div className ="col-md-8 login_container" id="login">
                        <div className="login_container">
                            <Logo />
                            <LoginForm />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}


export default Login;
// if (document.getElementById('login')) {
//     console.log("Rendering Login component");
//     ReactDOM.render(<Login />, document.getElementById('login'));
// } else {
//     console.log("Element with ID 'login' not found");
// }