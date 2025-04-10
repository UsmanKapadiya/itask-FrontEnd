import requests from "./api";

const LoginServices = {
    login: async (body) => {
        return requests.post(`/user-login`, body);
    },
    register:async(body) =>{
        return requests.post(`/user-register`, body);

    },
   
};

export default LoginServices;
