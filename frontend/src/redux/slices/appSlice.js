import { createSlice } from '@reduxjs/toolkit'
const appSlice = createSlice({ name: 'app', initialState: { apiStatus: 'unchecked' }, reducers: { setApiStatus: (state, action) => { state.apiStatus = action.payload } } })
export const { setApiStatus } = appSlice.actions
export default appSlice.reducer
