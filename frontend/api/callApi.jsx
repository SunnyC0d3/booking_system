import api from './axiosInstance';

const API_CALL_TYPE = ['client', 'auth'];

const callApi = async ({path, method = 'GET', data = {}, authType = 'client'}) => {
    try {
        if(!API_CALL_TYPE.includes(authType)) {
            throw new Error('API call type not correct.');
        }

        const response = await api.request({
            url: '/proxy',
            method: 'POST',
            credentials: 'include',
            data: {
                path,
                method,
                authType,
                data
            }
        });

        return response.data;
    } catch (error) {
        throw new Error(error.response?.data?.message || error.message);
    }
};

export default callApi;